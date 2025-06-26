#!/bin/bash

# Deploy sites-health Lambda function

FUNCTION_NAME="wpab-sites-health"
REGION="ap-southeast-2"

echo "Deploying $FUNCTION_NAME Lambda function..."

# Install dependencies
echo "Installing dependencies..."
npm install

# Create deployment package
echo "Creating deployment package..."
zip -r function.zip . -x "*.sh" -x "*.git*"

# Update Lambda function code
echo "Updating Lambda function..."
aws lambda update-function-code \
    --function-name $FUNCTION_NAME \
    --zip-file fileb://function.zip \
    --region $REGION

# Update function configuration
echo "Updating function configuration..."
aws lambda update-function-configuration \
    --function-name $FUNCTION_NAME \
    --runtime nodejs20.x \
    --handler index.handler \
    --timeout 30 \
    --memory-size 256 \
    --environment Variables="{SITES_TABLE=wpab-sites,HEALTH_TABLE=wpab-site-health,CORS_ORIGIN=https://portal.wpautoblogger.ai}" \
    --region $REGION

# Clean up
rm function.zip

echo "Deployment complete!"