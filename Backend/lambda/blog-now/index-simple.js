const AWS = require('aws-sdk');
const dynamodb = new AWS.DynamoDB.DocumentClient();
const lambda = new AWS.Lambda();

exports.handler = async (event) => {
    console.log('Blog Now event:', JSON.stringify(event, null, 2));
    
    try {
        const body = JSON.parse(event.body);
        const { topicId, siteId } = body;
        
        if (!topicId || !siteId) {
            return {
                statusCode: 400,
                headers: {
                    'Access-Control-Allow-Origin': '*',
                    'Access-Control-Allow-Credentials': true,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    message: 'Missing required parameters: topicId and siteId are required' 
                })
            };
        }
        
        // For now, return a mock response to test the flow
        const jobId = `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
        
        return {
            statusCode: 200,
            headers: {
                'Access-Control-Allow-Origin': '*',
                'Access-Control-Allow-Credentials': true,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message: 'Blog generation queued',
                jobId,
                status: 'queued',
                estimatedTime: '1-3 minutes'
            })
        };
        
    } catch (error) {
        console.error('Blog Now error:', error);
        return {
            statusCode: 500,
            headers: {
                'Access-Control-Allow-Origin': '*',
                'Access-Control-Allow-Credentials': true,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                message: 'Internal server error',
                error: error.message 
            })
        };
    }
};