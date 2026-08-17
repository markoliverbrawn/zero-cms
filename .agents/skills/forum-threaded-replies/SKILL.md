---
name: forum-threaded-replies
description: Explains the Forum module's self-referential nested-reply threading model, reply overlay modals, and admin moderation relational shortcuts. Use when working on forum_posts threading, reply modals, or forum admin moderation views.
---

# Interactive Community Forum & Nested Threaded Replies

To support collaborative communities without sacrificing security and multi-tenant isolation, the Forum module implements a highly interactive, style-separated nested reply engine.

## 1. Relational Threading Schema (`forum_posts`)

Reply postings utilize a self-referential `parent_id` column inside the `forum_posts` table to model deep conversation trees. Thread replies are automatically grouped and resolved relative to their root parent post, completely avoiding flat-list clutter.

## 2. Modular Reply Overlay Modals

Thread reply dialogs are nested completely within the native `.forum-container` DOM element wrapper inside `src/Views/themes/forum/forum_thread.php` to prevent theme style-bleed. Features a fully native Markdown cursor-formatting helper toolbar bound dynamically in parent JavaScript onto post reply forms.

## 3. Admin Moderation & Relational Shortcuts

Back-office lists convert raw foreign UUIDv7 keys into direct clickable relational shortcuts (e.g. converting `board_id` to a clickable Board model edit form, or `thread_id` to its parent Thread sheet). Restricts sensitive relationship associations (such as user, board, and thread mappings) to read-only status widgets inside the edit panel, preventing unauthorized privilege escalation or context cross-pollution.
