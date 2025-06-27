const AWS = require('aws-sdk');
const dynamodb = new AWS.DynamoDB.DocumentClient();

const JOBS_TABLE = process.env.JOBS_TABLE || 'wpab-jobs';

exports.handler = async (event) => {
    console.log('Job Status event:', JSON.stringify(event, null, 2));
    
    try {
        const jobId = event.pathParameters?.jobId;
        
        if (!jobId) {
            return {
                statusCode: 400,
                headers: {
                    'Access-Control-Allow-Origin': process.env.CORS_ORIGIN || '*',
                    'Access-Control-Allow-Credentials': true,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    message: 'Job ID is required' 
                })
            };
        }
        
        // Get job details
        const result = await dynamodb.get({
            TableName: JOBS_TABLE,
            Key: { jobId }
        }).promise();
        
        if (!result.Item) {
            return {
                statusCode: 404,
                headers: {
                    'Access-Control-Allow-Origin': process.env.CORS_ORIGIN || '*',
                    'Access-Control-Allow-Credentials': true,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    message: 'Job not found' 
                })
            };
        }
        
        return {
            statusCode: 200,
            headers: {
                'Access-Control-Allow-Origin': process.env.CORS_ORIGIN || '*',
                'Access-Control-Allow-Credentials': true,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(result.Item)
        };
        
    } catch (error) {
        console.error('Job status error:', error);
        return {
            statusCode: 500,
            headers: {
                'Access-Control-Allow-Origin': process.env.CORS_ORIGIN || '*',
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