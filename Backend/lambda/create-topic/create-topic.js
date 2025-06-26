const { DynamoDBClient } = require('@aws-sdk/client-dynamodb');
const { DynamoDBDocumentClient, PutCommand } = require('@aws-sdk/lib-dynamodb');
const { v4: uuidv4 } = require('uuid');

const client = new DynamoDBClient({});
const dynamodb = DynamoDBDocumentClient.from(client);

exports.handler = async (event) => {
    console.log('Create topic event:', JSON.stringify(event, null, 2));
    
    try {
        // Parse request body
        const body = typeof event.body === 'string' ? JSON.parse(event.body) : event.body;
        
        // Extract required fields
        const { title, siteId, userId, tenantId } = body;
        
        if (!title || !siteId || !userId) {
            return {
                statusCode: 400,
                headers: {
                    'Access-Control-Allow-Origin': process.env.CORS_ORIGIN || '*',
                    'Access-Control-Allow-Credentials': true
                },
                body: JSON.stringify({ 
                    message: 'Missing required fields: title, siteId, userId' 
                })
            };
        }
        
        // Create topic record
        const topicId = uuidv4();
        const now = new Date().toISOString();
        
        const topic = {
            id: topicId,
            title: title,
            siteId: siteId,
            userId: userId,
            tenantId: tenantId || userId,
            status: 'pending',
            createdAt: now,
            updatedAt: now,
            ...(body.description && { description: body.description }),
            ...(body.keywords && { keywords: body.keywords }),
            ...(body.priority && { priority: body.priority })
        };
        
        // Save to DynamoDB
        const params = {
            TableName: process.env.TOPICS_TABLE || 'wpab-topics',
            Item: topic
        };
        
        await dynamodb.send(new PutCommand(params));
        
        return {
            statusCode: 201,
            headers: {
                'Access-Control-Allow-Origin': process.env.CORS_ORIGIN || '*',
                'Access-Control-Allow-Credentials': true
            },
            body: JSON.stringify({
                success: true,
                topic: topic
            })
        };
        
    } catch (error) {
        console.error('Create topic error:', error);
        return {
            statusCode: 500,
            headers: {
                'Access-Control-Allow-Origin': process.env.CORS_ORIGIN || '*',
                'Access-Control-Allow-Credentials': true
            },
            body: JSON.stringify({
                message: 'Internal server error',
                error: error.message
            })
        };
    }
};