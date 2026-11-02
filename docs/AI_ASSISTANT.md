# AI Assistant

A chat page (`ai_assistant.php`, in the side menu under Dashboard) where people ask
questions about the system and get answers drawn from the records their role can see.

## Setting it up

Tools > AI Assistant (`ai_assistant_settings.php`), super admins only:

1. Pick the provider: **Claude (Anthropic)**, **OpenAI** or **OpenRouter**.
2. Paste that provider's API key. It is stored in `cdb_ai_settings` and the page only ever shows a masked form.
3. Pick the model. Suggestions are listed (Claude: `claude-opus-5-5` default, `claude-sonnet-5-5`,
   `claude-haiku-5-5`, `claude-fable-5-1`); **Load Models** fetches the provider's live list, and any id can be typed.
4. **Test Connection**, tick **Switch the assistant on**, **Save**.

Options: who can use it (everyone, or staff only), thinking effort (Claude), lookups per question,
whether the assistant may suggest pages, and house rules added to every conversation.

Tables are created on first use (`cdb_ai_settings`, `cdb_ai_conversations`, `cdb_ai_messages`,
`cdb_ai_questions`, `cdb_ai_knowledge`, `cdb_ai_page_ideas`); the database user needs CREATE once.
The server needs the PHP curl extension and outbound HTTPS to the provider.
`config/config.php` may define `CDP_AI_CLAUDE_URL`, `CDP_AI_OPENAI_URL` or `CDP_AI_OPENROUTER_URL`
to send requests through a gateway.

## What it can see

The model answers by calling read-only tools (`helpers/ai_assistant_tools.php`). Every tool applies
the asking user's permissions and scope on the server, the same way the list pages do:

| Role | Sees |
| --- | --- |
| Staff / admin / super admin | every record, in each area their list permission opens |
| Agency | records of their branch |
| Driver | records assigned to them |
| Customer | their own shipments, packages, pickups, pre-alerts and bills |

Tools: overview counts, counts grouped by status / day / month / branch / driver / customer / mode /
payment, record lists, one record with its tracking history, change log and consolidation, issues
(problem statuses, not moving, unpaid), the activity log (own actions only without Activity Logs
permission), finance totals, customer search (staff), the pages the user can open, and statuses.
The assistant cannot change data.

## Learning

- Every question is logged with the areas it touched (`cdb_ai_questions`). Administrators see the
  totals, areas, roles, recent and unanswered questions on the settings page.
- **What The Assistant Knows**: facts administrators add (or tell the assistant to remember in a chat)
  go into every conversation.
- **Suggested Pages**: the assistant files a page idea when a question has no good page; **Analyse
  Questions** asks the model to group recent questions into themes and propose new pages. Repeated
  ideas collect votes; each can be marked New / Planned / Built / Dismissed.
