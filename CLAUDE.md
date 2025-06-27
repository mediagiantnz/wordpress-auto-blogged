# WordPress Auto Blogger Architecture Rules

## CRITICAL ARCHITECTURE UNDERSTANDING

### WordPress Plugin Role (IMPORTANT)
The WordPress plugin (`/WordPress/Plugin/`) serves ONLY as a REST API connector. It does NOT:
- ❌ Generate content
- ❌ Handle scheduling
- ❌ Generate topics
- ❌ Call LLM APIs (OpenAI, Claude, Gemini)
- ❌ Manage any business logic

The WordPress plugin ONLY:
- ✅ Provides REST API endpoints for the cloud infrastructure
- ✅ Receives content from Lambda functions and publishes to WordPress
- ✅ Queries WordPress data (categories, authors, users)
- ✅ Manages authentication via Site Key (X-Site-Key header)
- ✅ Acts as a bridge between cloud services and WordPress

### Cloud Infrastructure Role
All business logic happens in AWS:
- **React Portal** (portal.wpautoblogger.ai) - User interface for managing everything
- **DynamoDB** - Stores all data (sites, topics, schedules, content)
- **Lambda Functions** - Handle all processing:
  - Content generation (calls LLM APIs)
  - Topic generation
  - Schedule processing
  - Site health checks
- **EventBridge** - Triggers scheduled tasks
- **API Gateway** - Provides APIs for React portal

### WordPress Plugin Components

#### Main Plugin (`/WordPress/Plugin/`)
Legacy full plugin with local scheduling/generation features - NOT USED in cloud architecture

#### Cloud Plugin (`/WordPress/Plugin-Cloud/`)
Minimal plugin that ONLY provides REST API endpoints:
- `/wp-json/wpab-cloud/v1/publish` - Publish content
- `/wp-json/wpab-cloud/v1/health` - Health check
- `/wp-json/wpab-cloud/v1/categories` - Get categories
- `/wp-json/wpab-cloud/v1/users` - Get users
- `/wp-json/wpab-cloud/v1/verify` - Verify site connection

### Data Flow
1. User configures everything in React portal
2. Data stored in DynamoDB
3. EventBridge triggers Lambda functions
4. Lambda generates content using LLM APIs
5. Lambda calls WordPress REST API to publish
6. WordPress plugin receives content and creates posts

### Development Guidelines
- When working on scheduling → Focus on Lambda/EventBridge
- When working on content generation → Focus on Lambda functions
- When working on WordPress integration → Focus on REST API endpoints only
- Never add business logic to WordPress plugin
- Keep WordPress plugin minimal and focused on API connectivity