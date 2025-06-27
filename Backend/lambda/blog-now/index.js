const AWS = require('aws-sdk');
const https = require('https');
const http = require('http');
const { URL } = require('url');

const dynamodb = new AWS.DynamoDB.DocumentClient();
const lambda = new AWS.Lambda();

// Table names
const JOBS_TABLE = process.env.JOBS_TABLE || 'wpab-jobs';
const SITES_TABLE = process.env.SITES_TABLE || 'wpab-sites';
const TOPICS_TABLE = process.env.TOPICS_TABLE || 'wpab-topics';

exports.handler = async (event) => {
    console.log('Blog Now event:', JSON.stringify(event, null, 2));
    
    try {
        const body = JSON.parse(event.body);
        const { topicId, siteId } = body;
        
        if (!topicId || !siteId) {
            return {
                statusCode: 400,
                headers: {
                    'Access-Control-Allow-Origin': process.env.CORS_ORIGIN || '*',
                    'Access-Control-Allow-Credentials': true,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    message: 'Missing required parameters: topicId and siteId are required' 
                })
            };
        }
        
        // Create job ID
        const jobId = `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
        
        // Create initial job entry
        const jobItem = {
            jobId,
            topicId,
            siteId,
            status: 'queued',
            createdAt: new Date().toISOString(),
            updatedAt: new Date().toISOString()
        };
        
        await dynamodb.put({
            TableName: JOBS_TABLE,
            Item: jobItem
        }).promise();
        
        // Trigger async processing
        await lambda.invoke({
            FunctionName: process.env.PROCESSOR_FUNCTION || 'wpab-blog-processor',
            InvocationType: 'Event',
            Payload: JSON.stringify({
                jobId,
                topicId,
                siteId
            })
        }).promise();
        
        return {
            statusCode: 200,
            headers: {
                'Access-Control-Allow-Origin': process.env.CORS_ORIGIN || '*',
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