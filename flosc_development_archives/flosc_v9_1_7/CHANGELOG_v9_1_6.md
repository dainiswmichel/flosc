# FLOSK v9.1.6 Changelog

## Release Date: 2025-01-20

## Major Features: RAG (Retrieval Augmented Generation) System

### 🎯 Core RAG Implementation
- **NEW:** User Access Management system (visitor/guest/member)
- **NEW:** Content filtering by access level
- **NEW:** RAG Manager with AI search tools
- **NEW:** `/chat-rag` endpoint for AI-powered chat with search capabilities

### 🔍 AI Search Tools
The AI can now search your WordPress content dynamically:
1. **search_knowledge_base** - Search markdown knowledge base files
2. **search_posts** - Search WordPress posts/lessons
3. **get_lesson_content** - Retrieve specific lesson content

### 👥 Three-Tier Access System
- **Visitor** - Not logged in, limited access
- **Guest** - Logged in, can see more
- **Member** - Full access to all content

### 🎓 How It Works
1. User sends message to `/chat-rag` endpoint
2. AI receives user's access level
3. When AI needs information, it calls search tools
4. Your plugin searches WordPress and filters by access level
5. AI receives filtered results
6. AI responds as a guide, pointing to your content

### 📝 Key Files Added
- `includes/class-user-access-manager.php` - Manages user access levels
- `includes/class-content-filter.php` - Filters content by access level
- `includes/class-rag-manager.php` - Handles AI search tools

### 🔧 Implementation Notes
The RAG system is designed so:
- AI acts as a GUIDE, not a teacher
- AI points users to your WordPress content
- Content is filtered server-side (secure)
- Works with existing quiz system
- Supports time-limited pricing offers

### 🎨 Usage Example
```php
// User asks: "What should I work on?"
// AI searches WordPress for lessons
// AI sees user got 30% on quiz
// AI responds: "Based on your quiz, I recommend Lesson 7: [link]"
```

### 🚀 Next Steps for Admins
1. Add Anthropic API key in settings
2. Upload knowledge base files to `/wp-content/uploads/flosc-knowledge/`
3. Tag posts with `_flosc_lesson_number` meta
4. Set posts access level with `_flosc_access_level` meta
5. Test with `/chat-rag` endpoint

### 📋 Content Format
Knowledge base files should use:
```markdown
### ACCESS LEVEL: VISITOR
Public content here

### ACCESS LEVEL: GUEST  
Content for logged-in users

### ACCESS LEVEL: MEMBER
Full member content
```

WordPress posts use `<!--more-->` tag to separate public from member content.

### ⚙️ Technical Details
- Anthropic Claude API with tool calling
- Conversation loop (up to 5 tool calls per message)
- Server-side access control (secure)
- Integrated with existing FLOSC infrastructure

### 🐛 Known Limitations
- Knowledge base directory must be created manually
- Vector search not yet implemented (keyword search only)
- Daisy-chain AI (3 AIs) not yet implemented
- Payment integration pending

### 📖 Documentation
See FLOSK_DEVELOPMENT_TASKS.md for complete implementation roadmap.

---

## Upgrade Instructions

1. Backup your current FLOSK installation
2. Replace plugin files with v9.1.6
3. Go to WordPress admin → FLOSK → AI Configuration
4. Add your Anthropic API key
5. Create `/wp-content/uploads/flosc-knowledge/` directory
6. Test with the new `/chat-rag` endpoint

---

## Backwards Compatibility

✅ Fully backwards compatible with v9.1.5
✅ Existing `/chat` endpoint still works (IVR mode)
✅ New `/chat-rag` endpoint optional

---

## Credits

Developed with Claude Sonnet 4.5 assistance
FLOSK Framework by Dainis Michel
