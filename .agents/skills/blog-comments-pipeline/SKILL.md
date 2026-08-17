---
name: blog-comments-pipeline
description: Explains the Blog module's relational commenting and moderation pipeline — blog_comments schema, pending/approved moderation flow, and comment-notifier email dispatch. Use when working on blog comment submission, moderation UI, or comment_notifiers email behavior.
---

# Relational Blog Commenting & Moderation Pipeline

To support user engagement without sacrificing multi-tenant isolation or backend performance, the Blog module features a robust relational commenting and administrative moderation pipeline.

## 1. Multi-Tenant Database Archiving (`blog_comments`)

```sql
CREATE TABLE blog_comments (
    id VARCHAR(36) PRIMARY KEY,
    site_id VARCHAR(36) NOT NULL,
    post_id VARCHAR(36) NOT NULL,
    author_name VARCHAR(255) NOT NULL,
    author_email VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending', -- pending, approved
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    INDEX (site_id),
    INDEX (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 2. Comment Status Moderation by Default

* For spam prevention and content protection, newly submitted public comments default strictly to `'pending'`.
* Public blog list views exclusively load approved comments using left-join pagination queries.
* Administrators moderate pending comment submissions inside a read-only list widget, switching them to `'approved'` inside the panel to publish.

## 3. Decoupled Comment Notifiers & TCP Mail Dispatching

* Blog articles support multiple-select boxes inside model edit sheets allowing publishers to select multiple administrative users.
* Stored as a serialized JSON array of database **User ID UUIDv7 strings** in the `blog_posts` table's `comment_notifiers` column.
* Upon public comment submissions, the server resolves selected users' email addresses dynamically and pushes responsive HTML mail notification headers via raw SMTP TCP sockets (see the `raw-tcp-emailer` skill).
