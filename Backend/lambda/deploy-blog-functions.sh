#!/bin/bash

# Deploy blog-now and blog-processor Lambda functions

REGION="ap-southeast-2"
ROLE_ARN="arn:aws:iam::235494808985:role/wpab-lambda-execution-role"

echo "Deploying WordPress Auto Blogger Lambda functions..."

# Deploy blog-now function
echo "1. Deploying wpab-blog-now..."
cd blog-now
npm install
zip -r function.zip . -x "*.sh" -x "*.git*"

# Check if function exists
if aws lambda get-function --function-name wpab-blog-now --region $REGION 2>/dev/null; then
    echo "Updating existing function..."
    aws lambda update-function-code \
        --function-name wpab-blog-now \
        --zip-file fileb://function.zip \
        --region $REGION
    
    aws lambda update-function-configuration \
        --function-name wpab-blog-now \
        --runtime nodejs20.x \
        --handler index.handler \
        --timeout 30 \
        --memory-size 256 \
        --environment Variables="{JOBS_TABLE=wpab-jobs,SITES_TABLE=wpab-sites,TOPICS_TABLE=wpab-topics,PROCESSOR_FUNCTION=wpab-blog-processor,CORS_ORIGIN=https://portal.wpautoblogger.ai}" \
        --region $REGION
else
    echo "Creating new function..."
    aws lambda create-function \
        --function-name wpab-blog-now \
        --runtime nodejs20.x \
        --role $ROLE_ARN \
        --handler index.handler \
        --timeout 30 \
        --memory-size 256 \
        --zip-file fileb://function.zip \
        --environment Variables="{JOBS_TABLE=wpab-jobs,SITES_TABLE=wpab-sites,TOPICS_TABLE=wpab-topics,PROCESSOR_FUNCTION=wpab-blog-processor,CORS_ORIGIN=https://portal.wpautoblogger.ai}" \
        --region $REGION
fi

rm function.zip
cd ..

# Deploy blog-processor function
echo "2. Deploying wpab-blog-processor..."
cd blog-processor
npm install
zip -r function.zip . -x "*.sh" -x "*.git*"

# Check if function exists
if aws lambda get-function --function-name wpab-blog-processor --region $REGION 2>/dev/null; then
    echo "Updating existing function..."
    aws lambda update-function-code \
        --function-name wpab-blog-processor \
        --zip-file fileb://function.zip \
        --region $REGION
    
    aws lambda update-function-configuration \
        --function-name wpab-blog-processor \
        --runtime nodejs20.x \
        --handler index.handler \
        --timeout 300 \
        --memory-size 512 \
        --environment Variables="{JOBS_TABLE=wpab-jobs,SITES_TABLE=wpab-sites,TOPICS_TABLE=wpab-topics,CONTENT_TABLE=wpab-content}" \
        --region $REGION
else
    echo "Creating new function..."
    aws lambda create-function \
        --function-name wpab-blog-processor \
        --runtime nodejs20.x \
        --role $ROLE_ARN \
        --handler index.handler \
        --timeout 300 \
        --memory-size 512 \
        --zip-file fileb://function.zip \
        --environment Variables="{JOBS_TABLE=wpab-jobs,SITES_TABLE=wpab-sites,TOPICS_TABLE=wpab-topics,CONTENT_TABLE=wpab-content}" \
        --region $REGION
fi

rm function.zip
cd ..

echo "Deployment complete!"

# Add tags to the functions
echo "Adding tags..."
aws lambda tag-resource \
    --resource arn:aws:lambda:$REGION:235494808985:function:wpab-blog-now \
    --tags ClientName=AutomateAi,Project="WordPress Auto Blogger",Environment=production,Owner=MediaGiant,CostCenter=Operations \
    --region $REGION

aws lambda tag-resource \
    --resource arn:aws:lambda:$REGION:235494808985:function:wpab-blog-processor \
    --tags ClientName=AutomateAi,Project="WordPress Auto Blogger",Environment=production,Owner=MediaGiant,CostCenter=Operations \
    --region $REGION

echo "Tags applied!"