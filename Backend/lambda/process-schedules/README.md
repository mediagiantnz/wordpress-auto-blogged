# Process Schedules Lambda Function

This Lambda function processes schedules for WordPress Auto Blogger, implementing random posting times within user-defined windows.

## Architecture

- **Function Name**: wpab-process-schedules
- **Runtime**: Node.js 20.x
- **Trigger**: EventBridge rule running every 15 minutes
- **Timeout**: 300 seconds
- **Memory**: 512 MB

## Features

1. **Random Time Selection**: Posts are published at random times within the user-defined window (e.g., 9 AM - 5 PM)
2. **Multi-Site Support**: Processes schedules for all sites with enabled schedules
3. **Topic Management**: Automatically selects approved topics and marks them as published
4. **Frequency Support**: Handles daily, weekly, biweekly, and monthly posting schedules

## How It Works

1. EventBridge triggers the function every 15 minutes
2. Function scans the schedules table for schedules where `nextRunTime <= now`
3. For each schedule:
   - Retrieves site configuration
   - Finds approved topics for the site
   - Randomly selects topics based on `postsPerInterval`
   - Invokes the blog-now Lambda for each topic
   - Updates topic status to 'published'
   - Calculates and sets the next run time with a random time within the window

## Environment Variables

- `SCHEDULES_TABLE`: wpab-schedules
- `SITES_TABLE`: wpab-sites  
- `TOPICS_TABLE`: wpab-topics
- `BLOG_NOW_FUNCTION`: wpab-blog-now

## IAM Permissions

The function has permissions to:
- Read/write DynamoDB tables (schedules, sites, topics)
- Invoke the blog-now Lambda function
- Write CloudWatch logs

## Testing

To manually test the function:
```bash
aws lambda invoke --function-name wpab-process-schedules --region ap-southeast-2 output.json
```

## Deployment

Use the included deploy.sh script:
```bash
./deploy.sh
```

This will:
1. Package the function with dependencies
2. Create/update the Lambda function
3. Create the EventBridge rule
4. Set up proper permissions