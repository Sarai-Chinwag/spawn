# Spawn Chat Block

AI-powered chat interface for Spawn customers using `@extrachill/chat`.

## Overview

This block provides a complete chat interface that connects to Data Machine's REST API. It uses the `@extrachill/chat` package for the UI and handles:

- Multi-turn conversations with AI
- Session management (history, switching, deletion)
- File attachments (images, documents)
- Tool call display
- Responsive design with mobile support

## Installation

```bash
npm install
```

## Development

```bash
npm start
```

## Build

```bash
npm run build
```

## Usage

Add the "Spawn AI Chat" block to any page or post. The block will:

1. Check if the user is logged in
2. Verify they have a Spawn customer account
3. Show a login prompt or error message if not
4. Render the full chat interface if authorized

## Configuration

Block attributes (configurable in editor sidebar):

- `welcomeMessage` - Message shown in empty state
- `placeholder` - Placeholder text for input field
- `showSessions` - Whether to show session history sidebar

## REST API

The block talks to Data Machine's chat endpoints:

- `POST /wp-json/datamachine/v1/chat` - Send message
- `POST /wp-json/datamachine/v1/chat/continue` - Continue multi-turn
- `GET /wp-json/datamachine/v1/chat/sessions` - List sessions
- `GET /wp-json/datamachine/v1/chat/{session_id}` - Load session
- `DELETE /wp-json/datamachine/v1/chat/{session_id}` - Delete session

## Styling

Uses CSS custom properties from `@extrachill/chat` with Spawn branding overrides in `style.css`.

Key variables:
- `--spawn-primary` - Primary brand color (indigo)
- `--spawn-radius` - Border radius (16px)
- `--spawn-shadow` - Box shadow
