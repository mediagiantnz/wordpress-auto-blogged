resource "aws_iam_role" "wpautoblog_lambda_role" {
  name = "wpautoblog-lambda-role"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "lambda.amazonaws.com"
        }
      }
    ]
  })

  tags = {
    ClientName = "WPAutoBlogger"
    Project    = "WPAutoBlogger"
  }
}

resource "aws_iam_role_policy" "wpautoblog_lambda_policy" {
  name = "wpautoblog-lambda-policy"
  role = aws_iam_role.wpautoblog_lambda_role.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "dynamodb:GetItem",
          "dynamodb:PutItem",
          "dynamodb:UpdateItem",
          "dynamodb:Query",
          "dynamodb:Scan"
        ]
        Resource = [
          aws_dynamodb_table.wpautoblog_licenses.arn,
          "${aws_dynamodb_table.wpautoblog_licenses.arn}/index/*",
          aws_dynamodb_table.wpautoblog_sites.arn,
          "${aws_dynamodb_table.wpautoblog_sites.arn}/index/*"
        ]
      },
      {
        Effect = "Allow"
        Action = [
          "logs:CreateLogGroup",
          "logs:CreateLogStream",
          "logs:PutLogEvents"
        ]
        Resource = "arn:aws:logs:*:*:*"
      }
    ]
  })
}

resource "aws_lambda_function" "wpautoblog_license_activation" {
  filename         = "../lambda/license-activation/deployment.zip"
  function_name    = "wpautoblog-license-activation"
  role            = aws_iam_role.wpautoblog_lambda_role.arn
  handler         = "index.handler"
  runtime         = "nodejs20.x"
  timeout         = 30

  environment {
    variables = {
      LICENSES_TABLE = aws_dynamodb_table.wpautoblog_licenses.name
      SITES_TABLE    = aws_dynamodb_table.wpautoblog_sites.name
    }
  }

  tags = {
    ClientName = "WPAutoBlogger"
    Project    = "WPAutoBlogger"
  }
}

resource "aws_lambda_function" "wpautoblog_license_validation" {
  filename         = "../lambda/license-validation/deployment.zip"
  function_name    = "wpautoblog-license-validation"
  role            = aws_iam_role.wpautoblog_lambda_role.arn
  handler         = "index.handler"
  runtime         = "nodejs20.x"
  timeout         = 30

  environment {
    variables = {
      LICENSES_TABLE = aws_dynamodb_table.wpautoblog_licenses.name
      SITES_TABLE    = aws_dynamodb_table.wpautoblog_sites.name
    }
  }

  tags = {
    ClientName = "WPAutoBlogger"
    Project    = "WPAutoBlogger"
  }
}

resource "aws_lambda_permission" "wpautoblog_activation_api_gateway" {
  statement_id  = "AllowAPIGatewayInvoke"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.wpautoblog_license_activation.function_name
  principal     = "apigateway.amazonaws.com"
  source_arn    = "${aws_api_gateway_rest_api.wpautoblog_api.execution_arn}/*/*"
}

resource "aws_lambda_permission" "wpautoblog_validation_api_gateway" {
  statement_id  = "AllowAPIGatewayInvoke"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.wpautoblog_license_validation.function_name
  principal     = "apigateway.amazonaws.com"
  source_arn    = "${aws_api_gateway_rest_api.wpautoblog_api.execution_arn}/*/*"
}