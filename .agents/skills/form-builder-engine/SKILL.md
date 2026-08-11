---
name: form-builder-engine
description: Explains the decoupled Form Builder block engine — dynamic field schemas, server-side validation compilation, JSON archival of submissions, and the submissions viewer dashboard. Use when working on the form_builder block type, /api/v1/contact/submit, or the submissions admin model.
---

# Decoupled Form Builder & Archival Submissions Engine

Zero CMS features a fully dynamic, fully featured, and 100% styled-separated **Form Builder block** that enables designers to construct custom web forms dynamically inside the page builder.

## 1. Dynamic Field Schemas (`items` array)

Form blocks are saved as serialized JSON blocks containing an array of dynamic field specifications:

```json
{
  "type": "form_builder",
  "id": "cf_custom_recruitment",
  "recipient_email": "careers@zero.cms",
  "items": [
    {"name": "full_name", "label": "Full Name", "type": "text", "required": "1", "validation": "none"},
    {"name": "subject", "label": "Area of Interest", "type": "select", "required": "0", "options": "Engineering, Design, Marketing", "validation": "none"}
  ]
}
```

## 2. Dynamic Server-Side Validation Compilation

Upon submission, the API Controller loaded by `/api/v1/contact/submit` extracts the block's fields layout from the database. It dynamically compiles a matching verification rules matrix on the fly, running inputs through the core `Validator` engine (see the `input-validator` skill).

## 3. Self-Contained JSON Database Archival (`message`)

* Submissions pass validation and are persisted in the `form_submissions` table.
* All submitted inputs are mapped as `label => value` and archived as a **beautifully structured JSON string inside the `message` TEXT column**.
* It dynamically resolves and appends the source **Page Title** and **Form Title** inside the JSON string as metadata, preserving the form's state historically even if the page is later modified or deleted.

## 4. Custom Submissions Viewer Dashboard

* Registered as the `'submissions'` model in the admin core to render full search, pagination, and soft-delete lists out of the box.
* Features a custom, read-only display card inside `edit.php` (no inline styles, nested in `components.css`) that cleanly maps out the meta header, source pages, sender details, and displays every dynamic field's label and value in structured high-contrast list cards.
