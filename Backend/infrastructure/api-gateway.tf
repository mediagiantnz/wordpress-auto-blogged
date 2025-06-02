resource "aws_api_gateway_rest_api" "wpautoblog_api" {
  name        = "wpautoblog-api"
  description = "WP Auto Blogger License Management API"

  tags = {
    ClientName = "WPAutoBlogger"
    Project    = "WPAutoBlogger"
  }
}

resource "aws_api_gateway_resource" "wpautoblog_license_resource" {
  rest_api_id = aws_api_gateway_rest_api.wpautoblog_api.id
  parent_id   = aws_api_gateway_rest_api.wpautoblog_api.root_resource_id
  path_part   = "license"
}

resource "aws_api_gateway_resource" "wpautoblog_activate_resource" {
  rest_api_id = aws_api_gateway_rest_api.wpautoblog_api.id
  parent_id   = aws_api_gateway_resource.wpautoblog_license_resource.id
  path_part   = "activate"
}

resource "aws_api_gateway_resource" "wpautoblog_validate_resource" {
  rest_api_id = aws_api_gateway_rest_api.wpautoblog_api.id
  parent_id   = aws_api_gateway_resource.wpautoblog_license_resource.id
  path_part   = "validate"
}

# Activation endpoint
resource "aws_api_gateway_method" "wpautoblog_activate_post" {
  rest_api_id   = aws_api_gateway_rest_api.wpautoblog_api.id
  resource_id   = aws_api_gateway_resource.wpautoblog_activate_resource.id
  http_method   = "POST"
  authorization = "NONE"
}

resource "aws_api_gateway_integration" "wpautoblog_activate_integration" {
  rest_api_id = aws_api_gateway_rest_api.wpautoblog_api.id
  resource_id = aws_api_gateway_resource.wpautoblog_activate_resource.id
  http_method = aws_api_gateway_method.wpautoblog_activate_post.http_method

  integration_http_method = "POST"
  type                   = "AWS_PROXY"
  uri                    = aws_lambda_function.wpautoblog_license_activation.invoke_arn
}

# Validation endpoint
resource "aws_api_gateway_method" "wpautoblog_validate_post" {
  rest_api_id   = aws_api_gateway_rest_api.wpautoblog_api.id
  resource_id   = aws_api_gateway_resource.wpautoblog_validate_resource.id
  http_method   = "POST"
  authorization = "NONE"
}

resource "aws_api_gateway_integration" "wpautoblog_validate_integration" {
  rest_api_id = aws_api_gateway_rest_api.wpautoblog_api.id
  resource_id = aws_api_gateway_resource.wpautoblog_validate_resource.id
  http_method = aws_api_gateway_method.wpautoblog_validate_post.http_method

  integration_http_method = "POST"
  type                   = "AWS_PROXY"
  uri                    = aws_lambda_function.wpautoblog_license_validation.invoke_arn
}

# CORS for activation
resource "aws_api_gateway_method" "wpautoblog_activate_options" {
  rest_api_id   = aws_api_gateway_rest_api.wpautoblog_api.id
  resource_id   = aws_api_gateway_resource.wpautoblog_activate_resource.id
  http_method   = "OPTIONS"
  authorization = "NONE"
}

resource "aws_api_gateway_integration" "wpautoblog_activate_options_integration" {
  rest_api_id = aws_api_gateway_rest_api.wpautoblog_api.id
  resource_id = aws_api_gateway_resource.wpautoblog_activate_resource.id
  http_method = aws_api_gateway_method.wpautoblog_activate_options.http_method
  type        = "MOCK"

  request_templates = {
    "application/json" = jsonencode({
      statusCode = 200
    })
  }
}

resource "aws_api_gateway_method_response" "wpautoblog_activate_options_response" {
  rest_api_id = aws_api_gateway_rest_api.wpautoblog_api.id
  resource_id = aws_api_gateway_resource.wpautoblog_activate_resource.id
  http_method = aws_api_gateway_method.wpautoblog_activate_options.http_method
  status_code = "200"

  response_parameters = {
    "method.response.header.Access-Control-Allow-Headers" = true
    "method.response.header.Access-Control-Allow-Methods" = true
    "method.response.header.Access-Control-Allow-Origin"  = true
  }
}

resource "aws_api_gateway_integration_response" "wpautoblog_activate_options_integration_response" {
  rest_api_id = aws_api_gateway_rest_api.wpautoblog_api.id
  resource_id = aws_api_gateway_resource.wpautoblog_activate_resource.id
  http_method = aws_api_gateway_method.wpautoblog_activate_options.http_method
  status_code = aws_api_gateway_method_response.wpautoblog_activate_options_response.status_code

  response_parameters = {
    "method.response.header.Access-Control-Allow-Headers" = "'Content-Type,X-Amz-Date,Authorization,X-Api-Key,X-Amz-Security-Token'"
    "method.response.header.Access-Control-Allow-Methods" = "'OPTIONS,POST'"
    "method.response.header.Access-Control-Allow-Origin"  = "'*'"
  }
}

# CORS for validation
resource "aws_api_gateway_method" "wpautoblog_validate_options" {
  rest_api_id   = aws_api_gateway_rest_api.wpautoblog_api.id
  resource_id   = aws_api_gateway_resource.wpautoblog_validate_resource.id
  http_method   = "OPTIONS"
  authorization = "NONE"
}

resource "aws_api_gateway_integration" "wpautoblog_validate_options_integration" {
  rest_api_id = aws_api_gateway_rest_api.wpautoblog_api.id
  resource_id = aws_api_gateway_resource.wpautoblog_validate_resource.id
  http_method = aws_api_gateway_method.wpautoblog_validate_options.http_method
  type        = "MOCK"

  request_templates = {
    "application/json" = jsonencode({
      statusCode = 200
    })
  }
}

resource "aws_api_gateway_method_response" "wpautoblog_validate_options_response" {
  rest_api_id = aws_api_gateway_rest_api.wpautoblog_api.id
  resource_id = aws_api_gateway_resource.wpautoblog_validate_resource.id
  http_method = aws_api_gateway_method.wpautoblog_validate_options.http_method
  status_code = "200"

  response_parameters = {
    "method.response.header.Access-Control-Allow-Headers" = true
    "method.response.header.Access-Control-Allow-Methods" = true
    "method.response.header.Access-Control-Allow-Origin"  = true
  }
}

resource "aws_api_gateway_integration_response" "wpautoblog_validate_options_integration_response" {
  rest_api_id = aws_api_gateway_rest_api.wpautoblog_api.id
  resource_id = aws_api_gateway_resource.wpautoblog_validate_resource.id
  http_method = aws_api_gateway_method.wpautoblog_validate_options.http_method
  status_code = aws_api_gateway_method_response.wpautoblog_validate_options_response.status_code

  response_parameters = {
    "method.response.header.Access-Control-Allow-Headers" = "'Content-Type,X-Amz-Date,Authorization,X-Api-Key,X-Amz-Security-Token'"
    "method.response.header.Access-Control-Allow-Methods" = "'OPTIONS,POST'"
    "method.response.header.Access-Control-Allow-Origin"  = "'*'"
  }
}

# Deployment
resource "aws_api_gateway_deployment" "wpautoblog_deployment" {
  depends_on = [
    aws_api_gateway_integration.wpautoblog_activate_integration,
    aws_api_gateway_integration.wpautoblog_validate_integration,
    aws_api_gateway_integration.wpautoblog_activate_options_integration,
    aws_api_gateway_integration.wpautoblog_validate_options_integration
  ]

  rest_api_id = aws_api_gateway_rest_api.wpautoblog_api.id
  stage_name  = "prod"
}

output "wpautoblog_api_url" {
  value = aws_api_gateway_deployment.wpautoblog_deployment.invoke_url
  description = "The URL for the WP Auto Blogger API"
}