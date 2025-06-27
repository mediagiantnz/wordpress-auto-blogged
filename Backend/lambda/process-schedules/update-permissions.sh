#!/bin/bash

# Update IAM permissions for process-schedules Lambda

REGION="ap-southeast-2"
ACCOUNT_ID="235494808985"
ROLE_NAME="wpab-lambda-execution-role"

echo "Creating IAM policy for process-schedules Lambda..."

# Create the policy JSON
cat > process-schedules-policy.json <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "dynamodb:Scan",
                "dynamodb:GetItem",
                "dynamodb:UpdateItem",
                "dynamodb:Query"
            ],
            "Resource": [
                "arn:aws:dynamodb:$REGION:$ACCOUNT_ID:table/wpab-schedules",
                "arn:aws:dynamodb:$REGION:$ACCOUNT_ID:table/wpab-sites",
                "arn:aws:dynamodb:$REGION:$ACCOUNT_ID:table/wpab-topics",
                "arn:aws:dynamodb:$REGION:$ACCOUNT_ID:table/wpab-schedules/index/*"
            ]
        },
        {
            "Effect": "Allow",
            "Action": [
                "lambda:InvokeFunction"
            ],
            "Resource": [
                "arn:aws:lambda:$REGION:$ACCOUNT_ID:function:wpab-blog-now"
            ]
        },
        {
            "Effect": "Allow",
            "Action": [
                "logs:CreateLogGroup",
                "logs:CreateLogStream",
                "logs:PutLogEvents"
            ],
            "Resource": "arn:aws:logs:$REGION:$ACCOUNT_ID:*"
        }
    ]
}
EOF

# Create or update the policy
POLICY_NAME="wpab-process-schedules-policy"

# Check if policy exists
if aws iam get-policy --policy-arn "arn:aws:iam::$ACCOUNT_ID:policy/$POLICY_NAME" 2>/dev/null; then
    echo "Policy exists, creating new version..."
    aws iam create-policy-version \
        --policy-arn "arn:aws:iam::$ACCOUNT_ID:policy/$POLICY_NAME" \
        --policy-document file://process-schedules-policy.json \
        --set-as-default
else
    echo "Creating new policy..."
    aws iam create-policy \
        --policy-name $POLICY_NAME \
        --policy-document file://process-schedules-policy.json
fi

# Attach the policy to the role
echo "Attaching policy to role..."
aws iam attach-role-policy \
    --role-name $ROLE_NAME \
    --policy-arn "arn:aws:iam::$ACCOUNT_ID:policy/$POLICY_NAME"

# Clean up
rm process-schedules-policy.json

echo "IAM permissions updated successfully!"