const { DynamoDBClient } = require('@aws-sdk/client-dynamodb');
const { DynamoDBDocumentClient, ScanCommand, UpdateCommand, GetCommand } = require('@aws-sdk/lib-dynamodb');
const { LambdaClient, InvokeCommand } = require('@aws-sdk/client-lambda');

const dynamoClient = new DynamoDBClient({});
const dynamodb = DynamoDBDocumentClient.from(dynamoClient);
const lambda = new LambdaClient({});

exports.handler = async (event) => {
    console.log('Processing schedules at:', new Date().toISOString());
    
    try {
        // Get current time
        const now = new Date();
        const currentTime = now.toISOString();
        
        // Scan for schedules that should run
        // Note: In production, you'd want to use a GSI query on nextRunTime
        const scanParams = {
            TableName: process.env.SCHEDULES_TABLE || 'wpab-schedules',
            FilterExpression: 'nextRunTime <= :now AND enabled = :enabled',
            ExpressionAttributeValues: {
                ':now': currentTime,
                ':enabled': true
            }
        };
        
        const result = await dynamodb.send(new ScanCommand(scanParams));
        console.log(`Found ${result.Items?.length || 0} schedules to process`);
        
        // Process each schedule
        const promises = (result.Items || []).map(async (schedule) => {
            try {
                await processSchedule(schedule);
            } catch (error) {
                console.error(`Error processing schedule ${schedule.scheduleId}:`, error);
            }
        });
        
        await Promise.all(promises);
        
        return {
            statusCode: 200,
            body: JSON.stringify({
                message: 'Schedules processed successfully',
                processed: result.Items?.length || 0
            })
        };
        
    } catch (error) {
        console.error('Error processing schedules:', error);
        return {
            statusCode: 500,
            body: JSON.stringify({
                message: 'Error processing schedules',
                error: error.message
            })
        };
    }
};

async function processSchedule(schedule) {
    console.log('Processing schedule:', JSON.stringify(schedule, null, 2));
    
    // Get site details
    const siteParams = {
        TableName: process.env.SITES_TABLE || 'wpab-sites',
        Key: { siteId: schedule.siteId }
    };
    
    const siteResult = await dynamodb.send(new GetCommand(siteParams));
    const site = siteResult.Item;
    
    if (!site) {
        console.error(`Site not found: ${schedule.siteId}`);
        return;
    }
    
    // Get approved topics for this site
    const topicsParams = {
        TableName: process.env.TOPICS_TABLE || 'wpab-topics',
        FilterExpression: 'siteId = :siteId AND #status = :status',
        ExpressionAttributeNames: {
            '#status': 'status'
        },
        ExpressionAttributeValues: {
            ':siteId': schedule.siteId,
            ':status': 'approved'
        }
    };
    
    const topicsResult = await dynamodb.send(new ScanCommand(topicsParams));
    const topics = topicsResult.Items || [];
    
    if (topics.length === 0) {
        console.log(`No approved topics for site ${schedule.siteId}`);
        // Still update next run time
        await updateNextRunTime(schedule);
        return;
    }
    
    // Pick random topics based on postsPerInterval
    const postsToPublish = Math.min(schedule.postsPerInterval || 1, topics.length);
    const selectedTopics = getRandomItems(topics, postsToPublish);
    
    console.log(`Publishing ${selectedTopics.length} topics for site ${schedule.siteId}`);
    
    // Invoke blog-now Lambda for each topic
    for (const topic of selectedTopics) {
        try {
            const invokeParams = {
                FunctionName: process.env.BLOG_NOW_FUNCTION || 'wpab-blog-now',
                InvocationType: 'Event', // Async invocation
                Payload: JSON.stringify({
                    topicId: topic.id,
                    siteId: schedule.siteId,
                    userId: schedule.userId,
                    autoPublish: true
                })
            };
            
            await lambda.send(new InvokeCommand(invokeParams));
            console.log(`Invoked blog-now for topic ${topic.id}`);
            
            // Update topic status to 'published'
            await updateTopicStatus(topic.id, 'published');
            
        } catch (error) {
            console.error(`Error publishing topic ${topic.id}:`, error);
        }
    }
    
    // Update next run time
    await updateNextRunTime(schedule);
}

function getRandomItems(array, count) {
    const shuffled = [...array].sort(() => 0.5 - Math.random());
    return shuffled.slice(0, count);
}

async function updateTopicStatus(topicId, status) {
    const params = {
        TableName: process.env.TOPICS_TABLE || 'wpab-topics',
        Key: { id: topicId },
        UpdateExpression: 'SET #status = :status, updatedAt = :updatedAt',
        ExpressionAttributeNames: {
            '#status': 'status'
        },
        ExpressionAttributeValues: {
            ':status': status,
            ':updatedAt': new Date().toISOString()
        }
    };
    
    await dynamodb.send(new UpdateCommand(params));
}

async function updateNextRunTime(schedule) {
    const nextRunTime = calculateNextRunTime(schedule);
    
    const params = {
        TableName: process.env.SCHEDULES_TABLE || 'wpab-schedules',
        Key: { scheduleId: schedule.scheduleId },
        UpdateExpression: 'SET nextRunTime = :nextRunTime, lastRunTime = :lastRunTime, updatedAt = :updatedAt',
        ExpressionAttributeValues: {
            ':nextRunTime': nextRunTime,
            ':lastRunTime': new Date().toISOString(),
            ':updatedAt': new Date().toISOString()
        }
    };
    
    await dynamodb.send(new UpdateCommand(params));
    console.log(`Updated next run time for schedule ${schedule.scheduleId} to ${nextRunTime}`);
}

function calculateNextRunTime(schedule) {
    const now = new Date();
    let nextRun = new Date();
    
    // Add interval based on frequency
    switch (schedule.frequency) {
        case 'daily':
            nextRun.setDate(nextRun.getDate() + 1);
            break;
        case 'weekly':
            nextRun.setDate(nextRun.getDate() + 7);
            break;
        case 'biweekly':
            nextRun.setDate(nextRun.getDate() + 14);
            break;
        case 'monthly':
            nextRun.setMonth(nextRun.getMonth() + 1);
            break;
        default:
            nextRun.setDate(nextRun.getDate() + 1); // Default to daily
    }
    
    // Handle different schedule modes
    if (schedule.scheduleMode === 'random' && schedule.scheduleTimeRanges && schedule.scheduleTimeRanges.length > 0) {
        // Pick a random time range
        const randomRange = schedule.scheduleTimeRanges[Math.floor(Math.random() * schedule.scheduleTimeRanges.length)];
        
        // Parse start and end times
        const [startHour, startMin] = randomRange.start.split(':').map(Number);
        const [endHour, endMin] = randomRange.end.split(':').map(Number);
        
        // Calculate random minutes within the window
        const startMinutes = startHour * 60 + startMin;
        const endMinutes = endHour * 60 + endMin;
        const randomMinutes = Math.floor(Math.random() * (endMinutes - startMinutes)) + startMinutes;
        
        const randomHour = Math.floor(randomMinutes / 60);
        const randomMinute = randomMinutes % 60;
        
        nextRun.setHours(randomHour, randomMinute, 0, 0);
    } else if (schedule.scheduleTimes && schedule.scheduleTimes.length > 0) {
        // Fixed times mode - pick a random fixed time
        const randomTime = schedule.scheduleTimes[Math.floor(Math.random() * schedule.scheduleTimes.length)];
        const [hour, minute] = randomTime.split(':').map(Number);
        nextRun.setHours(hour, minute, 0, 0);
    } else {
        // Fallback to legacy format or defaults
        const startHour = parseInt(schedule.startHour || '9');
        const endHour = parseInt(schedule.endHour || '17');
        
        const startMinutes = startHour * 60;
        const endMinutes = endHour * 60;
        const randomMinutes = Math.floor(Math.random() * (endMinutes - startMinutes)) + startMinutes;
        
        const randomHour = Math.floor(randomMinutes / 60);
        const randomMinute = randomMinutes % 60;
        
        nextRun.setHours(randomHour, randomMinute, 0, 0);
    }
    
    // If the calculated time is in the past (can happen if we're running late), 
    // add another interval
    if (nextRun <= now) {
        return calculateNextRunTime(schedule);
    }
    
    return nextRun.toISOString();
}