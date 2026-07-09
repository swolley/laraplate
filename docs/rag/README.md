# Laraplate RAG corpus (application level)

This folder holds **global** documentation you want available to the documentation assistant / RAG.

## Who reads what

| Audience | Typical questions | How they reach RAG |
|----------|-------------------|-------------------|
| **End user** (application operator) | “How do I approve a modification?”, “Where is the grid export?” | In-app **chat** (`ChatService`), when FAQ/RAG is enabled and the message is treated as a question (or `use_rag: true` in context) |
| **Developer** (extends Laraplate) | “How does ACL inheritance work?”, “Which event triggers indexing?” | Terminal **`php artisan ai:laraplate-help`** (REPL or `--question=`) |

Both paths use the **same indexed corpus** (`ai:index-docs`). Split content by **writing style and scope**, not by separate indexes (unless you later add filtered roots via `--path` or `AI_FAQ_DOCS_PATH`):

- **User-oriented** docs: tasks, UI flows, permissions from an operator perspective, troubleshooting for daily use.
- **Developer-oriented** docs: architecture, events, commands, config keys, extension points, module boundaries.

Place files under `docs/rag/` or `Modules/<Module>/docs/rag/` according to which module/feature they describe.

## Not the same as Elasticsearch / Scout indexing

| Concern | Corpus location | Command / trigger |
|---------|-----------------|-------------------|
| **FAQ / assistant RAG** | `docs/rag/`, `Modules/*/docs/rag/` | `php artisan ai:index-docs` |
| **Search index (models)** | Scout engine + embeddings tables | `ModelRequiresIndexing`, `queueMakeSearchable()` |

Event orchestration (moderation + search pre-processing) is documented for humans in `Modules/Core/docs/EVENT_ORCHESTRATION.md` and mirrored for the assistant under `Modules/Core/docs/rag/EVENT_ORCHESTRATION.md` (plus `Modules/AI/docs/rag/`, `Modules/CMS/docs/rag/` where relevant).

## Convention

- Application: `docs/rag/`
- Modules: `Modules/{ModuleName}/docs/rag/`
- General module docs (`Modules/*/docs/*.md` **without** `rag/`) are **not** indexed by default — copy or summarize into `docs/rag/` when the assistant should answer from them.

Indexing without `--path` uses `rag_paths()`; see `docs/README.md` for rules (native vs custom modules, env paths).

## Write docs for retrieval, not for marketing

For best answer quality, prefer:

- stable headings (capability-oriented sections)
- short focused paragraphs
- explicit operational language (what, when, how, limits)
- concrete command names, route prefixes, setting keys, and workflow steps
- troubleshooting snippets for common failures

Avoid vague descriptions that do not map to code behavior.

## Command

```bash
php artisan ai:index-docs
```

Useful options:

- `--path=`: index only a specific file or directory
- `--full`: wipe the vector store and rebuild

Terminal assistant command:

```bash
php artisan ai:laraplate-help
php artisan ai:laraplate-help --question="How does ACL inheritance work?"
```

## Adding project-specific documentation

You can:

1. Place files under `docs/rag/` or `Modules/<Module>/docs/rag/`, or
2. Set `AI_FAQ_DOCS_PATH` for extra relative subpaths (and optional absolute paths per `rag_paths()` rules).

## Recommended module document structure

Use a consistent section model for each major feature:

- Purpose
- Capabilities
- HowToUse
- InternalFlow
- Configuration
- PermissionsAndSecurity
- ErrorsAndTroubleshooting
- PerformanceAndLimits
- FAQPrompts

This structure improves chunk recall and keeps answers deterministic.

