# AI Configuration Files (Knowledge Base)

Runtime storage directory for AI context markdown files.

---

## Purpose

Files uploaded via **WordPress Admin → FLOSC → AI Knowledge** are stored in this directory.

These files provide custom knowledge, context, and instructions to the AI assistant,
allowing you to tailor responses to your specific product, service, or domain.

---

## Usage

### Adding Knowledge Files

1. **Via WordPress Admin:**
   - Navigate to: **WordPress Admin → FLOSC → AI Knowledge**
   - Upload `.md` files or create them inline using the editor
   - Files are immediately available to the AI system

2. **Via FTP/SSH:**
   - Upload `.md` files directly to this directory
   - Files are automatically discovered and loaded

### File Format

Only **Markdown (`.md`)** files are supported.

**Example: `product-knowledge.md`**
```markdown
# Product Information

## Features
- Feature 1: Real-time AI assistance
- Feature 2: Multi-language support
- Feature 3: Adaptive learning paths

## Common Questions

**Q: How do I reset my progress?**
A: Go to Settings → Progress → Reset Quiz Data

**Q: What payment methods are accepted?**
A: We accept credit cards via Stripe and ClickBank marketplace.

## Technical Specifications
- Framework: FLOSC v6.0+
- WordPress: 5.8 or higher
- PHP: 7.4+ required
```

## Purpose

Files uploaded to this directory provide **custom context and knowledge** to the AI assistant. When users interact with the chatbot, these files are automatically included in the AI's knowledge base.

## Usage

### Via WordPress Admin
1. Navigate to: **WordPress Admin → FLOSC → AI Knowledge**
2. Upload `.md` files or create them inline using the editor
3. Files are automatically loaded into AI context on every request

### File Format
Files should be markdown (`.md`) for best results:

```markdown
# Product Knowledge

## Core Features
- Feature 1: Description
- Feature 2: Description

## Common Questions
- Q: Question here?
- A: Answer here...

## Technical Specifications
- Specification 1
- Specification 2
```

### How It Works

When a user interacts with the AI assistant:
1. Plugin scans `ai_configuration_files/` directory
2. Loads all `.md` files found
3. Appends content to AI system prompt as "Knowledge Base"
4. AI uses this context to provide accurate, product-specific responses

**Example:** If you upload `product_features.md` containing your product specifications,
the AI will reference that information when answering user questions.

## File Management

**Upload Methods:**
1. **WordPress Admin:** FLOSC → AI Knowledge → Upload File
2. **Create Inline:** Use the admin editor to create files directly
3. **FTP/SFTP:** Manually upload `.md` files to this directory

**Supported Formats:**
- `.md` (Markdown) - Primary format
- Plain text with markdown syntax
- UTF-8 encoding required

**Naming Convention:**
- Use descriptive filenames: `product-features.md`, `faq.md`, `pricing.md`
- Avoid special characters (use hyphens instead of spaces)
- Keep filenames lowercase for consistency

## Technical Details

### How It Works
1. Files in this directory are scanned by `class-ai-provider-factory.php`
2. Content is automatically loaded into AI assistant context
3. AI uses this knowledge when responding to user queries

### File Format

Files should contain markdown-formatted knowledge:

\`\`\`markdown
# Product Features

## Core Features
- Feature 1: Description
- Feature 2: Description

## Pricing
- Basic: $X/month
- Pro: $Y/month

## Support
Contact support@example.com
\`\`\`

### Best Practices
- Use clear headings for topic organization
- Keep files focused on specific knowledge areas
- Update regularly as product/service changes
- Use markdown formatting for readability

## Technical Details

**Directory:** `/ai_configuration_files/`
**Loaded by:** `includes/class-ai-provider-factory.php`
**Format:** Markdown (.md files only)
**Size Limit:** 10MB per file (WordPress default upload limit)

## Historical Note

This directory was renamed from `ai_orientation_files/` in v06.02 for clarity.
The admin UI page was renamed from "AI Orientation" to "AI Knowledge" to better reflect its purpose as a knowledge base management system.

---

**Created:** 2026-01m-13d
**Version:** v06.02
**Maintained by:** Dainis Michel / FLOSC Framework
