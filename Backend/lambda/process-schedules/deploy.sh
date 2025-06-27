#!/bin/bash

# Deploy process-schedules Lambda function

REGION="ap-southeast-2"
ROLE_ARN="arn:aws:iam::235494808985:role/wpab-lambda-execution-role"

echo "Deploying wpab-process-schedules Lambda function..."

# Package the function
echo "Packaging function..."
npm install
zip -r function.zip . -x "*.sh" -x "*.git*" -x "deploy.sh"

# Check if function exists
if aws lambda get-function --function-name wpab-process-schedules --region $REGION 2>/dev/null; then
    echo "Updating existing function..."
    aws lambda update-function-code \
        --function-name wpab-process-schedules \
        --zip-file fileb://function.zip \
        --region $REGION
    
    aws lambda update-function-configuration \
        --function-name wpab-process-schedules \
        --runtime nodejs20.x \
        --handler index.handler \
        --timeout 300 \
        --memory-size 512 \
        --environment Variables="{SCHEDULES_TABLE=wpab-schedules,SITES_TABLE=wpab-sites,TOPICS_TABLE=wpab-topics,BLOG_NOW_FUNCTION=wpab-blog-now}" \
        --region $REGION
else
    echo "Creating new function..."
    aws lambda create-function \
        --function-name wpab-process-schedules \
        --runtime nodejs20.x \
        --role $ROLE_ARN \
        --handler index.handler \
        --timeout 300 \
        --memory-size 512 \
        --zip-file fileb://function.zip \
        --environment Variables="{SCHEDULES_TABLE=wpab-schedules,SITES_TABLE=wpab-sites,TOPICS_TABLE=wpab-topics,BLOG_NOW_FUNCTION=wpab-blog-now}" \
        --region $REGION
fi

rm function.zip

echo "Adding tags..."
aws lambda tag-resource \
    --resource arn:aws:lambda:$REGION:235494808985:function:wpab-process-schedules \
    --tags ClientName=AutomateAi,Project="WordPress Auto Blogger",Environment=production,Owner=MediaGiant,CostCenter=Operations \
    --region $REGION

echo "Lambda function deployment complete!"

# Create EventBridge rule
echo "Creating EventBridge rule..."
aws events put-rule \
    --name wpab-process-schedules \
    --description "Trigger schedule processing every 15 minutes" \
    --schedule-expression "rate(15 minutes)" \
    --region $REGION

# Add Lambda permission for EventBridge
echo "Adding Lambda permission for EventBridge..."
aws lambda add-permission \
    --function-name wpab-process-schedules \
    --statement-id AllowExecutionFromEventBridge \
    --action lambda:InvokeFunction \
    --principal events.amazonaws.com \
    --source-arn arn:aws:events:$REGION:235494808985:rule/wpab-process-schedules \
    --region $REGION

# Add EventBridge target
echo "Adding EventBridge target..."
aws events put-targets \
    --rule wpab-process-schedules \
    --targets "Id"="ProcessSchedulesLambdaTarget","Arn"="arn:aws:lambda:$REGION:235494808985:function:wpab-process-schedules" \
    --region $REGION

echo "EventBridge rule created and configured!"
echo "Process schedules Lambda is now running every 15 minutes."