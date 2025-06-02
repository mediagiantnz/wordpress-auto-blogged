const AWS = require('aws-sdk');
const crypto = require('crypto');

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
        const { licenseKey, siteUrl, email } = body;

        if (!licenseKey || !siteUrl || !email) {
            return {
                statusCode: 400,
                headers,
                body: JSON.stringify({ 
                    success: false, 
                    message: 'Missing required parameters' 
                })
            };
        }

        // Check if license exists and is valid
        const licenseResult = await dynamodb.get({
            TableName: LICENSES_TABLE,
            Key: { licenseKey }
        }).promise();

        if (!licenseResult.Item) {
            return {
                statusCode: 404,
                headers,
                body: JSON.stringify({ 
                    success: false, 
                    message: 'Invalid license key' 
                })
            };
        }

        const license = licenseResult.Item;

        // Check if license is active
        if (license.status !== 'active') {
            return {
                statusCode: 403,
                headers,
                body: JSON.stringify({ 
                    success: false, 
                    message: 'License is not active' 
                })
            };
        }

        // Check if license has reached activation limit
        const sitesResult = await dynamodb.query({
            TableName: SITES_TABLE,
            IndexName: 'licenseKey-index',
            KeyConditionExpression: 'licenseKey = :lk',
            ExpressionAttributeValues: {
                ':lk': licenseKey
            }
        }).promise();

        const activeSites = sitesResult.Items.filter(site => site.status === 'active');
        
        if (activeSites.length >= (license.activationLimit || 1)) {
            return {
                statusCode: 403,
                headers,
                body: JSON.stringify({ 
                    success: false, 
                    message: 'License activation limit reached' 
                })
            };
        }

        // Check if site already activated
        const existingSite = activeSites.find(site => site.siteUrl === siteUrl);
        if (existingSite) {
            return {
                statusCode: 200,
                headers,
                body: JSON.stringify({ 
                    success: true, 
                    message: 'Site already activated' 
                })
            };
        }

        // Activate the site
        const siteId = crypto.randomUUID();
        await dynamodb.put({
            TableName: SITES_TABLE,
            Item: {
                siteId,
                licenseKey,
                siteUrl,
                email,
                status: 'active',
                activatedAt: new Date().toISOString(),
                lastChecked: new Date().toISOString()
            }
        }).promise();

        // Update license last used
        await dynamodb.update({
            TableName: LICENSES_TABLE,
            Key: { licenseKey },
            UpdateExpression: 'SET lastUsed = :lu',
            ExpressionAttributeValues: {
                ':lu': new Date().toISOString()
            }
        }).promise();

        return {
            statusCode: 200,
            headers,
            body: JSON.stringify({ 
                success: true, 
                message: 'License activated successfully',
                siteId
            })
        };

    } catch (error) {
        console.error('Error:', error);
        return {
            statusCode: 500,
            headers,
            body: JSON.stringify({ 
                success: false, 
                message: 'Internal server error' 
            })
        };
    }
};