const AWS = require('aws-sdk');

const dynamodb = new AWS.DynamoDB.DocumentClient();
const LICENSES_TABLE = process.env.LICENSES_TABLE;
const SITES_TABLE = process.env.SITES_TABLE;

exports.handler = async (event) => {
    const headers = {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Headers': 'Content-Type',
        'Access-Control-Allow-Methods': 'OPTIONS,POST,GET'
    };

    if (event.httpMethod === 'OPTIONS') {
        return {
            statusCode: 200,
            headers,
            body: ''
        };
    }

    try {
        const body = JSON.parse(event.body);
        const { licenseKey, siteUrl } = body;

        if (!licenseKey || !siteUrl) {
            return {
                statusCode: 400,
                headers,
                body: JSON.stringify({ 
                    valid: false, 
                    message: 'Missing required parameters' 
                })
            };
        }

        // Check if license exists and is valid
        const licenseResult = await dynamodb.get({
            TableName: LICENSES_TABLE,
            Key: { licenseKey }
        }).promise();

        if (!licenseResult.Item || licenseResult.Item.status !== 'active') {
            return {
                statusCode: 200,
                headers,
                body: JSON.stringify({ 
                    valid: false, 
                    message: 'Invalid or inactive license' 
                })
            };
        }

        const license = licenseResult.Item;

        // Check if site is activated
        const sitesResult = await dynamodb.query({
            TableName: SITES_TABLE,
            IndexName: 'licenseKey-siteUrl-index',
            KeyConditionExpression: 'licenseKey = :lk AND siteUrl = :su',
            ExpressionAttributeValues: {
                ':lk': licenseKey,
                ':su': siteUrl
            }
        }).promise();

        if (!sitesResult.Items || sitesResult.Items.length === 0) {
            return {
                statusCode: 200,
                headers,
                body: JSON.stringify({ 
                    valid: false, 
                    message: 'Site not activated for this license' 
                })
            };
        }

        const site = sitesResult.Items[0];

        if (site.status !== 'active') {
            return {
                statusCode: 200,
                headers,
                body: JSON.stringify({ 
                    valid: false, 
                    message: 'Site activation has been revoked' 
                })
            };
        }

        // Update last checked timestamp
        await dynamodb.update({
            TableName: SITES_TABLE,
            Key: { 
                siteId: site.siteId
            },
            UpdateExpression: 'SET lastChecked = :lc',
            ExpressionAttributeValues: {
                ':lc': new Date().toISOString()
            }
        }).promise();

        return {
            statusCode: 200,
            headers,
            body: JSON.stringify({ 
                valid: true, 
                message: 'License is valid',
                expiresAt: license.expiresAt,
                features: license.features || {}
            })
        };

    } catch (error) {
        console.error('Error:', error);
        return {
            statusCode: 500,
            headers,
            body: JSON.stringify({ 
                valid: false, 
                message: 'Internal server error' 
            })
        };
    }
};