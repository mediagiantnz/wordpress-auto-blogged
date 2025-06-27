const AWS = require('aws-sdk');
const https = require('https');
const http = require('http');
const { URL } = require('url');
const crypto = require('crypto');

const dynamodb = new AWS.DynamoDB.DocumentClient();
const secretsManager = new AWS.SecretsManager();

// AI SDKs
const { Anthropic } = require('@anthropic-ai/sdk');
const { OpenAI } = require('openai');

// Table names
const JOBS_TABLE = process.env.JOBS_TABLE || 'wpab-jobs';
const SITES_TABLE = process.env.SITES_TABLE || 'wpab-sites';
const TOPICS_TABLE = process.env.TOPICS_TABLE || 'wpab-topics';
const CONTENT_TABLE = process.env.CONTENT_TABLE || 'wpab-content';

exports.handler = async (event) => {
    console.log('Blog Processor event:', JSON.stringify(event, null, 2));
    
    const { jobId, topicId, siteId } = event;
    
    try {
        // Update job status to processing
        await updateJobStatus(jobId, 'processing', { 
            startedAt: new Date().toISOString() 
        });
        
        // Get site and topic details
        const [site, topic] = await Promise.all([
            getSiteDetails(siteId),
            getTopicDetails(topicId, siteId)
        ]);
        
        if (!site || !topic) {
            throw new Error('Site or topic not found');
        }
        
        console.log('Processing blog for site:', site.name, 'topic:', topic.title);
        
        // STEP 1: Validate WordPress connection BEFORE generating content
        console.log('Validating WordPress connection...');
        const wpValidation = await validateWordPress(site);
        
        if (!wpValidation.isValid) {
            throw new Error(`WordPress validation failed: ${wpValidation.error}`);
        }
        
        console.log('WordPress validation successful');
        
        // Update job with title
        await updateJobStatus(jobId, 'processing', { 
            title: topic.title 
        });
        
        // STEP 2: Generate content using AI
        console.log('Generating content...');
        const content = await generateContent(site, topic);
        
        // Save generated content
        await saveContent(jobId, siteId, topicId, content);
        
        // STEP 3: Publish to WordPress
        console.log('Publishing to WordPress...');
        const publishResult = await publishToWordPress(site, content);
        
        if (!publishResult.success) {
            throw new Error(`Failed to publish to WordPress: ${publishResult.error}`);
        }
        
        // Update job status to completed
        await updateJobStatus(jobId, 'completed', {
            completedAt: new Date().toISOString(),
            wordpressPostId: publishResult.postId,
            wordpressUrl: publishResult.url
        });
        
        // Update topic status
        await updateTopicStatus(topicId, siteId, 'published', {
            publishedAt: new Date().toISOString(),
            wordpressPostId: publishResult.postId
        });
        
        return {
            statusCode: 200,
            body: JSON.stringify({
                message: 'Blog processed successfully',
                jobId,
                postId: publishResult.postId,
                url: publishResult.url
            })
        };
        
    } catch (error) {
        console.error('Blog processing error:', error);
        
        // Update job status to failed
        await updateJobStatus(jobId, 'failed', {
            failedAt: new Date().toISOString(),
            error: {
                message: error.message,
                type: error.constructor.name
            }
        });
        
        return {
            statusCode: 500,
            body: JSON.stringify({
                message: 'Blog processing failed',
                error: error.message
            })
        };
    }
};

// Validate WordPress connection and API
async function validateWordPress(site) {
    try {
        const { url, auth } = site.wordpress;
        const apiUrl = new URL('/wp-json/wp/v2/posts', url);
        
        // Create auth header
        const authHeader = Buffer.from(`${auth.username}:${auth.password}`).toString('base64');
        
        return new Promise((resolve) => {
            const options = {
                hostname: apiUrl.hostname,
                port: apiUrl.port || (apiUrl.protocol === 'https:' ? 443 : 80),
                path: apiUrl.pathname + '?per_page=1',
                method: 'GET',
                headers: {
                    'Authorization': `Basic ${authHeader}`,
                    'Content-Type': 'application/json'
                },
                timeout: 10000
            };
            
            const req = (apiUrl.protocol === 'https:' ? https : http).request(options, (res) => {
                let data = '';
                res.on('data', chunk => data += chunk);
                res.on('end', () => {
                    if (res.statusCode === 200) {
                        resolve({ isValid: true });
                    } else if (res.statusCode === 401) {
                        resolve({ 
                            isValid: false, 
                            error: 'Invalid WordPress credentials' 
                        });
                    } else if (res.statusCode === 404) {
                        resolve({ 
                            isValid: false, 
                            error: 'WordPress REST API not found. Ensure permalinks are enabled.' 
                        });
                    } else {
                        resolve({ 
                            isValid: false, 
                            error: `WordPress API returned status ${res.statusCode}: ${res.statusMessage}` 
                        });
                    }
                });
            });
            
            req.on('error', (error) => {
                resolve({ 
                    isValid: false, 
                    error: `Cannot connect to WordPress: ${error.message}` 
                });
            });
            
            req.on('timeout', () => {
                req.destroy();
                resolve({ 
                    isValid: false, 
                    error: 'WordPress connection timeout' 
                });
            });
            
            req.end();
        });
    } catch (error) {
        return { 
            isValid: false, 
            error: `WordPress validation error: ${error.message}` 
        };
    }
}

// Generate content using AI
async function generateContent(site, topic) {
    const aiProvider = site.settings?.aiProvider || 'openai';
    const apiKey = await getApiKey(aiProvider);
    
    const prompt = buildPrompt(site, topic);
    
    switch (aiProvider) {
        case 'anthropic':
            return await generateWithAnthropic(apiKey, prompt);
        case 'openai':
        default:
            return await generateWithOpenAI(apiKey, prompt);
    }
}

// Build content generation prompt
function buildPrompt(site, topic) {
    return `Write a comprehensive blog post about "${topic.title}" for ${site.name}.

Guidelines:
- Write in ${site.settings?.tone || 'professional'} tone
- Target audience: ${site.settings?.audience || 'general readers'}
- Length: ${site.settings?.length || '800-1200'} words
- Include relevant keywords naturally
- Structure with clear headings and paragraphs
- Make it engaging and informative
- Include a compelling introduction and conclusion

Return the content in the following JSON format:
{
    "title": "The blog post title",
    "content": "The full HTML content of the blog post",
    "excerpt": "A brief excerpt (150-200 characters)",
    "seo_title": "SEO optimized title (60 characters max)",
    "seo_description": "SEO meta description (160 characters max)",
    "keywords": ["keyword1", "keyword2", "keyword3"]
}`;
}

// Generate with OpenAI
async function generateWithOpenAI(apiKey, prompt) {
    const openai = new OpenAI({ apiKey });
    
    const response = await openai.chat.completions.create({
        model: 'gpt-4',
        messages: [
            { role: 'system', content: 'You are a professional blog writer.' },
            { role: 'user', content: prompt }
        ],
        temperature: 0.7,
        response_format: { type: 'json_object' }
    });
    
    return JSON.parse(response.choices[0].message.content);
}

// Generate with Anthropic
async function generateWithAnthropic(apiKey, prompt) {
    const anthropic = new Anthropic({ apiKey });
    
    const response = await anthropic.messages.create({
        model: 'claude-3-opus-20240229',
        messages: [
            { role: 'user', content: prompt }
        ],
        max_tokens: 4000,
        temperature: 0.7
    });
    
    return JSON.parse(response.content[0].text);
}

// Publish to WordPress
async function publishToWordPress(site, content) {
    try {
        const { url, auth } = site.wordpress;
        const apiUrl = new URL('/wp-json/wp/v2/posts', url);
        
        const authHeader = Buffer.from(`${auth.username}:${auth.password}`).toString('base64');
        
        const postData = JSON.stringify({
            title: content.title,
            content: content.content,
            excerpt: content.excerpt,
            status: site.settings?.autoPublish ? 'publish' : 'draft',
            categories: site.settings?.defaultCategories || [],
            tags: content.keywords?.map(k => ({ name: k })) || [],
            meta: {
                _yoast_wpseo_title: content.seo_title,
                _yoast_wpseo_metadesc: content.seo_description
            }
        });
        
        return new Promise((resolve) => {
            const options = {
                hostname: apiUrl.hostname,
                port: apiUrl.port || (apiUrl.protocol === 'https:' ? 443 : 80),
                path: apiUrl.pathname,
                method: 'POST',
                headers: {
                    'Authorization': `Basic ${authHeader}`,
                    'Content-Type': 'application/json',
                    'Content-Length': Buffer.byteLength(postData)
                }
            };
            
            const req = (apiUrl.protocol === 'https:' ? https : http).request(options, (res) => {
                let data = '';
                res.on('data', chunk => data += chunk);
                res.on('end', () => {
                    if (res.statusCode === 201) {
                        const post = JSON.parse(data);
                        resolve({
                            success: true,
                            postId: post.id,
                            url: post.link
                        });
                    } else {
                        let errorMessage = `Status ${res.statusCode}`;
                        try {
                            const errorData = JSON.parse(data);
                            errorMessage = errorData.message || errorData.error || errorMessage;
                        } catch (e) {
                            errorMessage = data || res.statusMessage;
                        }
                        resolve({
                            success: false,
                            error: errorMessage
                        });
                    }
                });
            });
            
            req.on('error', (error) => {
                resolve({
                    success: false,
                    error: error.message
                });
            });
            
            req.write(postData);
            req.end();
        });
    } catch (error) {
        return {
            success: false,
            error: error.message
        };
    }
}

// Helper functions
async function updateJobStatus(jobId, status, additionalData = {}) {
    const params = {
        TableName: JOBS_TABLE,
        Key: { jobId },
        UpdateExpression: 'SET #status = :status, #updatedAt = :updatedAt',
        ExpressionAttributeNames: {
            '#status': 'status',
            '#updatedAt': 'updatedAt'
        },
        ExpressionAttributeValues: {
            ':status': status,
            ':updatedAt': new Date().toISOString()
        }
    };
    
    // Add additional data
    Object.keys(additionalData).forEach(key => {
        params.UpdateExpression += `, #${key} = :${key}`;
        params.ExpressionAttributeNames[`#${key}`] = key;
        params.ExpressionAttributeValues[`:${key}`] = additionalData[key];
    });
    
    await dynamodb.update(params).promise();
}

async function getSiteDetails(siteId) {
    const result = await dynamodb.get({
        TableName: SITES_TABLE,
        Key: { id: siteId }
    }).promise();
    
    return result.Item;
}

async function getTopicDetails(topicId, siteId) {
    const result = await dynamodb.get({
        TableName: TOPICS_TABLE,
        Key: { 
            topicId: topicId,
            siteId: siteId
        }
    }).promise();
    
    return result.Item;
}

async function updateTopicStatus(topicId, siteId, status, additionalData = {}) {
    const params = {
        TableName: TOPICS_TABLE,
        Key: { 
            topicId: topicId,
            siteId: siteId
        },
        UpdateExpression: 'SET #status = :status, #updatedAt = :updatedAt',
        ExpressionAttributeNames: {
            '#status': 'status',
            '#updatedAt': 'updatedAt'
        },
        ExpressionAttributeValues: {
            ':status': status,
            ':updatedAt': new Date().toISOString()
        }
    };
    
    Object.keys(additionalData).forEach(key => {
        params.UpdateExpression += `, #${key} = :${key}`;
        params.ExpressionAttributeNames[`#${key}`] = key;
        params.ExpressionAttributeValues[`:${key}`] = additionalData[key];
    });
    
    await dynamodb.update(params).promise();
}

async function saveContent(jobId, siteId, topicId, content) {
    await dynamodb.put({
        TableName: CONTENT_TABLE,
        Item: {
            contentId: `${jobId}-${Date.now()}`,
            jobId,
            siteId,
            topicId,
            content,
            createdAt: new Date().toISOString()
        }
    }).promise();
}

async function getApiKey(provider) {
    const secretName = `wpab/${provider}/api-key`;
    try {
        const result = await secretsManager.getSecretValue({ SecretId: secretName }).promise();
        return result.SecretString;
    } catch (error) {
        console.error(`Failed to get API key for ${provider}:`, error);
        throw new Error(`API key not configured for ${provider}`);
    }
}