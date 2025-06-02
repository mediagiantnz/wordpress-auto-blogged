resource "aws_dynamodb_table" "wpautoblog_licenses" {
  name           = "wpautoblog-licenses"
  billing_mode   = "PAY_PER_REQUEST"
  hash_key       = "licenseKey"

  attribute {
    name = "licenseKey"
    type = "S"
  }

  attribute {
    name = "email"
    type = "S"
  }

  global_secondary_index {
    name            = "email-index"
    hash_key        = "email"
    projection_type = "ALL"
  }

  tags = {
    ClientName = "WPAutoBlogger"
    Project    = "WPAutoBlogger"
  }
}

resource "aws_dynamodb_table" "wpautoblog_sites" {
  name           = "wpautoblog-sites"
  billing_mode   = "PAY_PER_REQUEST"
  hash_key       = "siteId"

  attribute {
    name = "siteId"
    type = "S"
  }

  attribute {
    name = "licenseKey"
    type = "S"
  }

  attribute {
    name = "siteUrl"
    type = "S"
  }

  global_secondary_index {
    name            = "licenseKey-index"
    hash_key        = "licenseKey"
    projection_type = "ALL"
  }

  global_secondary_index {
    name            = "licenseKey-siteUrl-index"
    hash_key        = "licenseKey"
    range_key       = "siteUrl"
    projection_type = "ALL"
  }

  tags = {
    ClientName = "WPAutoBlogger"
    Project    = "WPAutoBlogger"
  }
}