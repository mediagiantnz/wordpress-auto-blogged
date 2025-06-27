# EventBridge rule to process schedules every 15 minutes
resource "aws_cloudwatch_event_rule" "process_schedules" {
  name                = "wpab-process-schedules"
  description         = "Trigger schedule processing every 15 minutes"
  schedule_expression = "rate(15 minutes)"
  
  tags = {
    ClientName = "AutomateAi"
    Project    = "WordPress Auto Blogger"
  }
}

# Lambda function for processing schedules
resource "aws_lambda_function" "process_schedules" {
  filename         = "../lambda/process-schedules.zip"
  function_name    = "wpab-process-schedules"
  role            = aws_iam_role.lambda_execution_role.arn
  handler         = "index.handler"
  runtime         = "nodejs20.x"
  timeout         = 300
  memory_size     = 512
  
  environment {
    variables = {
      SCHEDULES_TABLE   = aws_dynamodb_table.schedules.name
      SITES_TABLE       = aws_dynamodb_table.sites.name
      TOPICS_TABLE      = aws_dynamodb_table.topics.name
      BLOG_NOW_FUNCTION = aws_lambda_function.blog_now.function_name
    }
  }
  
  tags = {
    ClientName = "AutomateAi"
    Project    = "WordPress Auto Blogger"
  }
}

# Permission for EventBridge to invoke Lambda
resource "aws_lambda_permission" "allow_eventbridge_process_schedules" {
  statement_id  = "AllowExecutionFromEventBridge"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.process_schedules.function_name
  principal     = "events.amazonaws.com"
  source_arn    = aws_cloudwatch_event_rule.process_schedules.arn
}

# EventBridge target
resource "aws_cloudwatch_event_target" "process_schedules" {
  rule      = aws_cloudwatch_event_rule.process_schedules.name
  target_id = "ProcessSchedulesLambdaTarget"
  arn       = aws_lambda_function.process_schedules.arn
}

# IAM policy for process-schedules Lambda
resource "aws_iam_role_policy" "process_schedules_policy" {
  name = "wpab-process-schedules-policy"
  role = aws_iam_role.lambda_execution_role.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "dynamodb:Scan",
          "dynamodb:GetItem",
          "dynamodb:UpdateItem",
          "dynamodb:Query"
        ]
        Resource = [
          aws_dynamodb_table.schedules.arn,
          aws_dynamodb_table.sites.arn,
          aws_dynamodb_table.topics.arn,
          "${aws_dynamodb_table.schedules.arn}/index/*"
        ]
      },
      {
        Effect = "Allow"
        Action = [
          "lambda:InvokeFunction"
        ]
        Resource = [
          aws_lambda_function.blog_now.arn
        ]
      }
    ]
  })
}