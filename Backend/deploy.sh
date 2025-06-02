#!/bin/bash

# WP Auto Blogger AWS Deployment Script

set -e

echo "🚀 Starting WP Auto Blogger AWS deployment..."

# Check if AWS CLI is installed
if ! command -v aws &> /dev/null; then
    echo "❌ AWS CLI is not installed. Please install it first."
    exit 1
fi

# Check if Terraform is installed
if ! command -v terraform &> /dev/null; then
    echo "❌ Terraform is not installed. Please install it first."
    exit 1
fi

# Set AWS region (credentials should be configured via AWS CLI or environment variables)
export AWS_DEFAULT_REGION="ap-southeast-2"

# Check if AWS credentials are configured
if ! aws sts get-caller-identity &> /dev/null; then
    echo "❌ AWS credentials not configured. Please run 'aws configure' or set environment variables."
    echo "   AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY must be set."
    exit 1
fi

echo "📦 Building Lambda functions..."

# Build license-activation function
cd lambda/license-activation
npm install
npm run package
cd ../..

# Build license-validation function
cd lambda/license-validation
npm install
npm run package
cd ../..

echo "🏗️  Deploying infrastructure with Terraform..."

cd infrastructure

# Initialize Terraform
terraform init

# Plan the deployment
terraform plan -out=tfplan

# Apply the deployment
terraform apply tfplan

# Get the API Gateway URL
API_URL=$(terraform output -raw wpautoblog_api_url)

echo "✅ Deployment complete!"
echo ""
echo "📝 Important information:"
echo "API Gateway URL: $API_URL"
echo ""
echo "Add these values to your WordPress admin settings:"
echo "License Activation URL: ${API_URL}/license/activate"
echo "License Validation URL: ${API_URL}/license/validate"
echo ""
echo "⚠️  Security reminder:"
echo "1. Remove AWS credentials from this script"
echo "2. Use AWS IAM roles or environment variables instead"
echo "3. Store the API URLs securely in your WordPress database"