const { DynamoDBClient } = require('@aws-sdk/client-dynamodb');
const { DynamoDBDocumentClient, GetCommand, UpdateCommand } = require('@aws-sdk/lib-dynamodb');

const client = new DynamoDBClient({});
const dynamodb = DynamoDBDocumentClient.from(client);

// Using native https module instead of axios to avoid dependency issues
const https = require('https');

exports.handler = async (event) => {
    console.log('Health check event:', JSON.stringify(event, null, 2));
    
    const siteId = event.pathParameters?.siteId;
    
    if (!siteId) {
        return {
            statusCode: 400,
            headers: {
                'Access-Control-Allow-Origin': process.env.CORS_ORIGIN || '*',
                'Access-Control-Allow-Credentials': true
            },
            body: JSON.stringify({ message: 'Site ID is required' })
        };
    }
    
    try {
        // Get site details from DynamoDB
        const siteParams = {
            TableName: process.env.SITES_TABLE || 'wpab-sites',
            Key: { siteId: siteId }
        };
        
        const siteResult = await dynamodb.send(new GetCommand(siteParams));
        
        if (!siteResult.Item) {
            return {
                statusCode: 404,
                headers: {
                    'Access-Control-Allow-Origin': process.env.CORS_ORIGIN || '*',
                    'Access-Control-Allow-Credentials': true
                },
                body: JSON.stringify({ message: 'Site not found' })
            };
        }
        
        const site = siteResult.Item;
        const siteUrl = site.siteUrl;
        
        // Check site health
        const healthStatus = await checkSiteHealth(siteUrl);
        
        // Update health status in DynamoDB
        const updateParams = {
            TableName: process.env.HEALTH_TABLE || 'wpab-site-health',
            Key: { siteId: siteId },
            UpdateExpression: 'SET #status = :status, #lastChecked = :lastChecked, #responseTime = :responseTime, #statusCode = :statusCode',
            ExpressionAttributeNames: {
                '#status': 'status',
                '#lastChecked': 'lastChecked',
                '#responseTime': 'responseTime',
                '#statusCode': 'statusCode'
            },
            ExpressionAttributeValues: {
                ':status': healthStatus.healthy ? 'healthy' : 'unhealthy',
                ':lastChecked': new Date().toISOString(),
                ':responseTime': healthStatus.responseTime,
                ':statusCode': healthStatus.statusCode
            }
        };
        
        await dynamodb.send(new UpdateCommand(updateParams));
        
        return {
            statusCode: 200,
            headers: {
                'Access-Control-Allow-Origin': process.env.CORS_ORIGIN || '*',
                'Access-Control-Allow-Credentials': true
            },
            body: JSON.stringify({
                siteId: siteId,
                url: siteUrl,
                healthy: healthStatus.healthy,
                statusCode: healthStatus.statusCode,
                responseTime: healthStatus.responseTime,
                lastChecked: new Date().toISOString()
            })
        };
        
    } catch (error) {
        console.error('Health check error:', error);
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

// Function to check site health using native https module
function checkSiteHealth(url) {
    return new Promise((resolve) => {
        const startTime = Date.now();
        
        try {
            const urlObj = new URL(url);
            const options = {
                hostname: urlObj.hostname,
                port: urlObj.port || (urlObj.protocol === 'https:' ? 443 : 80),
                path: urlObj.pathname,
                method: 'GET',
                timeout: 10000 // 10 second timeout
            };
            
            const req = (urlObj.protocol === 'https:' ? https : require('http')).request(options, (res) => {
                const responseTime = Date.now() - startTime;
                
                // Consume response data to free up memory
                res.on('data', () => {});
                res.on('end', () => {
                    resolve({
                        healthy: res.statusCode >= 200 && res.statusCode < 400,
                        statusCode: res.statusCode,
                        responseTime: responseTime
                    });
                });
            });
            
            req.on('error', (error) => {
                console.error('Health check request error:', error);
                resolve({
                    healthy: false,
                    statusCode: 0,
                    responseTime: Date.now() - startTime,
                    error: error.message
                });
            });
            
            req.on('timeout', () => {
                req.destroy();
                resolve({
                    healthy: false,
                    statusCode: 0,
                    responseTime: Date.now() - startTime,
                    error: 'Request timeout'
                });
            });
            
            req.end();
            
        } catch (error) {
            console.error('Health check error:', error);
            resolve({
                healthy: false,
                statusCode: 0,
                responseTime: Date.now() - startTime,
                error: error.message
            });
        }
    });
}