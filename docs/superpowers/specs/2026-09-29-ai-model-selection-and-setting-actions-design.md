# AI model selection and setting actions: design

**Date:** 2026-09-29
**Modules:** `Modules/Core` (setting actions, command-managed choices, queue settings overlay), `Modules/AI` (model catalog, model settings, runtime wiring, translation)
**Related:** `2026-07-31-seeder-orchestration-design.md` (the reconciler whose realigned columns change here), `2026-09-27-core-approvals-subsystem-design.md` (the `Setting` approval rule gains an exemption), `2026-09-12-es-multilingual-index-and-multimodel-embeddings-design.md` (the embedding-model registry, deliberately untouched), `2026-09-18-media-ai-analysis-search-design.md` (its M18 master switch and M21 model registry are amended here)
**Status:** Implemented 2026-09-29 by `docs/superpowers/plans/2026-09-29-ai-model-selection-and-setting-actions.md`, whose delivery status lists where the code differs from this design (model refresh actions are queued). Approved 2026-09-29. Agreed section by section in conversation, before any code. Amended the same day, before any code: model defaults live in code, the per-feature env variables go, and the media-analysis master switch becomes a seeded setting. A cloud session had already produced an unrequested implementation on branch `ccr-1f855133-um9goj` in `laraplate`, `laraplate-core` and `laraplate-ai`; it was deleted unread on 2026-09-29, and nothing here derives from it.

---

## 1. Purpose

Two needs, the second serving the first.

1. **Choose the model of each AI feature from Filament.** Today provider and model come from env and config. An administrator picks, per feature, a provider and model from the list the provider itself publishes.
2. **Run a command from a setting row.** A seeded setting can carry an Artisan command that the settings grid runs on demand. The first use is refreshing the model lists of point 1.

Success means:

- A model changed in Settings is used by the next web request and by the next queued job, without restarting workers.
- A list only offers providers that are configured, and a provider outage never empties it.
- A saved value that is no longer offered is shown as an anomaly, never silently changed.
- No user can write or alter the command a setting runs.

## 2. Decisions

- **One setting per feature, value `provider:model`.** Provider and model are one choice, so changing provider never leaves a model of the old provider behind. Providers without a model catalogue use their name alone: `deepl`, `whisper`. The value splits on the **first** `:` because Ollama ids contain one (`ollama:llama3.2:3b`) and provider names never do. This mirrors the existing media-analysis registry, where a key already pairs a provider with a `service_model`.
- **Features covered:** chat, text generation, moderation, search orchestration, translation, media-analysis vision, media-analysis transcription, and the four services that today build an agent without naming a provider and so inherit the chat default: FAQ answers (`DocumentationAgent`), contextual suggestions, chat summary and memory (`MemoryService`), guardrails prompt-injection detection. Each gets its own setting rather than silently following chat: summaries, guardrails and suggestions are where a smaller model saves money. **Embeddings are excluded:** changing the embedding model changes the vector dimensions and forces a reindex, so they keep their profile registry and `AI_EMBEDDINGS_MODEL`.
- **Choices live in the `choices` column.** Rendering the form never calls a provider. A command refreshes them, from a button on the setting row or nightly.
- **A provider appears only if it supports the feature and is configured.** Configured means an API key for openai, anthropic, mistral and deepl, a URL for ollama and whisper. URL defaults are removed, so a missing URL means "not configured" for every provider, as it already does for Whisper.
- **Capability filter.** Where a provider declares capabilities, they decide. Where it does not, ids known to be incompatible (embedding, audio, image, moderation, legacy completion) are excluded and the rest is included: including an unknown model is the last resort, never excluding it.
- **Not configured versus failing.** A provider that is not configured loses its entries. A configured provider that fails to answer keeps the entries it had, recognised by their prefix, and the failure is reported. A night-time outage cannot empty a list.
- **A value no longer offered is reported, not reset.** The grid, the form and the command output flag it. Replacing it automatically would change the model in production without anyone deciding it; emptying it would break the feature.
- **`choices` is exempt from approval.** Users cannot edit it (the form does not expose it); only the refresh command writes it, and that command knows what it is doing.
- **Setting actions are two columns, `action_command` and `action_queued`.** Seeded only: not fillable, hidden from serialization, realigned on every seed so a changed command reaches existing installations. `action` alone was rejected as a name: it collides with Filament actions and with approval operations.
- **Placeholders are `{attribute}` of the setting**, substituted as quoted values so each stays one argument. `{value}` is refused on an encrypted setting.
- **Running an action requires the `update` permission on settings.** No confirmation, no bulk action. The permission may get its own scope later; today an action updates a field, so `update` fits.
- **Translation chooses DeepL or an AI model in one setting, with no fallback.** `features.translation.model` replaces Core's `translations.provider` (`deepl` or `ai`). The fallback is removed with `translations.fallback_to_ai`: no other feature has one, it changes provider (cost, quality, and where the text is sent) without anyone choosing it at that moment, and it was already off by default. Failures propagate so the queue retries.
- **Clean-ups agreed with the design:** `whisper_local` becomes `whisper`; the `whisper-api` profile (OpenAI `whisper-1`) is removed because nothing implements it; the Ollama URL loses its default.
- **A value managed by a setting has no config entry and no env variable; its default lives in code.** The module README already states the rule ("runtime settings, not env vars"), and `features.faq.enabled` follows it: no config key, default at the read site. A config default composed from env would be a second source of truth that lies: the seeder writes the value only when it creates the row, so changing the env afterwards does nothing and says nothing. Model defaults therefore sit in `AiModelFeature::defaultChoice()`, and the env variables that only chose a feature's provider or model are removed. Credentials, URLs and timeouts (`providers.*`) are infrastructure and stay in env. The cost: a fresh installation cannot preset models through env; they are chosen in Filament after the first refresh.
- **The media-analysis master switch becomes a seeded setting like every other AI switch** (amends M18 of the media spec). `features.media_analysis.enabled`, group `ai`, seeded off, read from config as `ai.features.media_analysis.enabled` with `false` in code. Today the gate looks up a row named `media_analysis.enabled` that is never seeded, so the switch is invisible in Filament and in practice only `AI_MEDIA_ANALYSIS_ENABLED` turns it on.
- **Queue workers re-apply the settings overlay before each job.** Without it, a changed setting reaches queued work only after a worker restart, and most AI features run queued.
- **Schema changes go into the create migration.** Pre-stable project: no alter migration, the developer database is rebuilt with `migrate:fresh --seed`.

## 3. Data

### `core_settings`

| Column | Change |
|---|---|
| `action_command` | **New.** `string`, nullable. Command line template with `{attribute}` placeholders, e.g. `ai:models:refresh --setting={name}`. |
| `action_queued` | **New.** `boolean`, not null, default `false`. `false` runs the command inside the request; `true` queues it. |

Both columns are added in `2024_03_30_170824_create_settings_table.php`.

### `Setting` model

- `$fillable` unchanged: neither new column is added, so neither the form nor the CRUD API can write them.
- `$hidden` gains both columns, so API responses (public settings are readable by guests) do not expose internal commands. Filament fills forms through `attributesToArray()`, which honours `$hidden`, so the form reads them from the record explicitly (section 4).
- `casts()` gains `action_queued => boolean`; `$attributes` gains `action_queued => false`.
- `requiresApprovalWhen()` exempts `choices` alongside `description` and `group_name`.

### Seed definitions

- `internalSettingsDefinition()` realigns `type`, `description`, `choices`, `is_internal`, **`action_command`, `action_queued`**.
- A variant for **command-managed choices** realigns the same columns **except `choices`**. For those rows `choices` is written when the row is created and never realigned afterwards; from then on the refresh command owns it. `SettingsCleaner` selects rows by module state, not by definition, so splitting a module's rows across two definitions deletes nothing.

### AI model settings

Seeded by the AI module through the command-managed variant, group `ai`, type `string`:

| Setting | Default (`AiModelFeature::defaultChoice()`) |
|---|---|
| `features.chat.model` | `ollama:llama3.2:3b` (today's defaults: `AI_CHAT_PROVIDER=ollama`, `OLLAMA_MODEL=llama3.2:3b`) |
| `features.text_generation.model` | `ollama:llama3.2:3b` |
| `features.moderation.model` | `ollama:llama3.2:3b` |
| `features.search_orchestration.model` | `ollama:llama3.2:3b` |
| `features.faq.model` | `ollama:llama3.2:3b` |
| `features.contextual_suggestions.model` | `ollama:llama3.2:3b` |
| `features.chat.summary.model` | `ollama:llama3.2:3b` |
| `features.guardrails.model` | `ollama:llama3.2:3b` (removed 2026-10-07, see the note under *Runtime wiring*) |
| `features.translation.model` | `deepl` |
| `features.media_analysis.vision.model` | `anthropic:claude-sonnet-5` |
| `features.media_analysis.transcription.model` | `whisper` |

For every row:

- No config entry. The overlay writes `ai.{setting name}` when the row exists; `AiModelChoice::forFeature()` reads that key with `defaultChoice()` as the fallback, so the code works before the first seed and in tests.
- Initial `value`: `defaultChoice()`. Initial `choices`: that value alone, so the select works before the first refresh.
- `action_command`: `ai:models:refresh --setting={name}`; `action_queued`: `false`.

### AI switch

`features.media_analysis.enabled`, type `boolean`, group `ai`, seeded `false` with the other AI runtime settings (`AIDatabaseSeeder::runtimeSettingDefinitions()`).

### Removed

- AI config keys: `features.chat.default_provider`, `features.text_generation.default_provider`, `features.text_generation.model`, `features.moderation.provider`, `features.search_orchestration.default_provider`, `features.translation.default_provider`, `features.media_analysis.enabled`, `features.media_analysis.capabilities` (the per-capability `active` key and static `models` lists), `providers.anthropic.model`.
- AI env: `AI_CHAT_PROVIDER`, `AI_TEXT_GENERATION_PROVIDER`, `AI_TEXT_GENERATION_MODEL`, `AI_MODERATION_PROVIDER`, `AI_COMMENT_MOD_PROVIDER`, `AI_SEARCH_ORCHESTRATION_PROVIDER`, `AI_TRANSLATION_PROVIDER`, `AI_MEDIA_ANALYSIS_ENABLED`, `AI_MEDIA_VISION_MODEL`, `AI_MEDIA_VISION_OLLAMA_MODEL`, `AI_MEDIA_TRANSCRIPTION_MODEL`, `AI_MEDIA_WHISPER_LOCAL_MODEL` (the Whisper model is chosen on the Whisper host through `WHISPER_MODEL`; Laraplate never sent it), `ANTHROPIC_MODEL` (no feature reads it once every feature passes its own model; `ProviderFactory` keeps `claude-sonnet-4-20250514` as a code constant for a caller naming the provider without a model).
- Changed AI env: `OLLAMA_API_URL` has no default.
- Kept AI env: `OPENAI_MODEL`, `OLLAMA_MODEL`, `MISTRAL_MODEL`. No AI feature reads them any more, but the embeddings factory does, and `ProviderFactory` uses them for a caller naming the provider without a model.
- `MediaAnalysisGate::SETTING_NAME` / `SETTING_GROUP` and its direct `PerModelSettingResolver` lookup of the never-seeded `media_analysis.enabled` row.
- Core settings: `translations.provider`, `translations.fallback_to_ai`. Only `Modules/AI` read them. `translations.cache.enabled` stays.

## 4. Components

### Core

**`SettingActionRunner`** (`Modules/Core/app/Services/`) runs the action of one setting:

1. Reads `action_command`. A setting without one is refused.
2. Resolves placeholders `{attribute}` against the setting's attributes. An unknown attribute is refused. `{value}` on an encrypted setting is refused. Scalars are cast to string, booleans become `true`/`false`, `null` becomes an empty string, arrays are JSON-encoded.
3. Substitutes each value wrapped in double quotes with `"` and `\` escaped. Symfony's `StringInput` tokenizer reads that as a single token, including in the `--option={name}` form, so a value with spaces or a leading `--` cannot add arguments or options.
4. Refuses a command not registered in Artisan: nothing runs.
5. Runs `Artisan::call($line)` when `action_queued` is `false`, returning exit code and output; `Artisan::queue($line)` when `true`.

Refusals throw a dedicated exception naming the reason. The runner returns a readonly result: exit code, output, queued flag.

**Settings grid** (`SettingsTable`), through the `actions` callback of `HasTable::configureTable()`:

- A row action, visible when `action_command` is set and the user holds `{connection}.core_settings.update` (the same `checkPermissionCached()` check the sibling actions use).
- Icon only (play), like its siblings; the tooltip shows the command template.
- No confirmation. Outcome as a notification: success with the last 1000 characters of output; danger when the exit code is not 0, when the runner refuses, or on an exception (also passed to `report()`); info "queued" for a queued command.
- The value column shows a setting with choices whose value is not among them in a warning colour, with a tooltip saying so. The check applies to every setting with choices.

**Settings form** (`SettingForm`):

- `action_command` and `action_queued` shown disabled and not dehydrated, like `name` and `type`, their state read from the record because `$hidden` keeps them out of the fill data.
- The `Select` built for a `string` setting with choices becomes `searchable()`.
- A saved value missing from the choices is added to the options labelled "(no longer available)", with helper text flagging it, so the select is never blank and saving does not drop the value.

**Queue overlay.** A `JobProcessing` listener registered by `CoreServiceProvider` re-applies `DatabaseConfigOverlay` from a fresh `PerModelSettingResolver`. The resolver is scoped, so the worker builds a new one per job, and it reads a persistent cache that the writing process invalidates: the cost is a cache read per job. It fixes stale settings in workers for every setting, not only the AI ones.

### AI

**`AiModelFeature`** (`Modules/AI/app/Enums/`), one case per feature, each exposing its setting name, supported providers, required capabilities and default choice (`defaultChoice()`, section 3):

| Feature | Providers | Required capabilities |
|---|---|---|
| chat | openai, ollama, mistral, anthropic | chat, tools (chat uses tool calling) |
| text generation, moderation, search orchestration, FAQ, contextual suggestions, chat summary, guardrails | openai, ollama, mistral, anthropic | chat |
| translation | deepl, openai, ollama, mistral, anthropic | chat (AI models) |
| vision | anthropic, openai, ollama | vision |
| transcription | whisper | none |

**`AiModelChoice`** value object: parses and formats `provider:model` or `provider`, split on the first `:`. `AiModelChoice::forFeature(AiModelFeature)` reads `ai.{setting name}`, which the database overlay writes, falling back to `defaultChoice()` when no row has been overlaid.

**Provider configuration check:** configured when the API key (openai, anthropic, mistral, deepl) or the URL (ollama, whisper) is non-empty.

**`ModelLister`** contract, one implementation per provider with a catalogue, each returning listed models (id plus capabilities, or "unknown"). Laravel HTTP client, connect timeout 3 s, request timeout 10 s, so a synchronous refresh never hangs on an unreachable host.

| Provider | Endpoint | Capabilities |
|---|---|---|
| OpenAI | `GET https://api.openai.com/v1/models`, Bearer | Not declared: denylist on the id (embedding, whisper, tts, audio, realtime, transcribe, dall-e, image, moderation, babbage, davinci, instruct); everything else is included for every feature |
| Anthropic | `GET https://api.anthropic.com/v1/models`, `x-api-key` + `anthropic-version`, paginated (`has_more`, `last_id` as `after_id`) | Every model counts as chat and tools; vision from `capabilities.image_input.supported`, assumed when `capabilities` is null |
| Mistral | `GET https://api.mistral.ai/v1/models`, Bearer | `capabilities` object: chat, function calling, vision |
| Ollama | `GET {url}/api/tags`, then `POST {url}/api/show` per model | `capabilities` list. Missing (older Ollama versions) or a failed `show` call for one model: unknown, included unless the name marks an embedding model. Only a failed `tags` call counts as the provider failing |

Endpoints, headers and response fields were verified against the providers' documentation on 2026-09-29 (OpenAI, Anthropic, Mistral, Ollama `api.md`). OpenAI is listed at its default endpoint because no factory reads `OPENAI_API_URL` today; the listing follows the endpoint chat actually uses.

DeepL and Whisper have no lister: when configured, their entry is the provider name.

**`ModelCatalog`** builds the choices of a set of features and a report:

- Calls each needed provider once per run and reuses the result across features.
- Per provider: not configured drops its entries; answering keeps the models with the required capabilities; failing keeps the entries it had in the current choices and records the error.
- Choices are sorted by provider, then by model id.
- The report gives, per provider, the outcome (count, not configured, or error) and, per feature, whether the current value is still offered.

**`ai:models:refresh {--setting=}`**:

- Without the option refreshes every model setting; with it, only the named one, calling only that feature's providers. An unknown name writes nothing and fails.
- Saves `choices` through the model, so the observer invalidates the settings cache as for any change. `choices` being exempt, a synchronous run from the grid is not captured for approval.
- Prints each provider's outcome, each setting's number of choices, and a warning line when the current value is no longer offered.
- Exit code `0` when every provider the run called answered; `1` when one failed (choices are still written, with that provider's previous entries) or the setting is unknown.
- Scheduled daily at 03:00 by `AIServiceProvider`, the way `CoreServiceProvider` registers Core's schedule.

**Runtime wiring.** Every consumer that reads a provider or a model from config reads `AiModelChoice::forFeature()` and calls `ProviderFactory::make($provider, $model)`:

- `ChatService`; the text-generation listener; `ModerationService`; `LlmSearchService`; `DocumentationAgent` (FAQ); `ContextualSuggestionService`; `MemoryService` (chat summary); `GuardrailsService`.
  *(Note 2026-10-07: `GuardrailsService` and the setting `features.guardrails.model` were removed on 2026-10-07, since nothing called the service; see `docs/superpowers/plans/2026-10-06-ai-module-neuron-review.md` (Task 11).)*
- `ProviderFactory::make()` called without a provider takes both provider and model from the chat choice, so any caller not listed above keeps following chat as it does today. Called with a provider and no model, it keeps that provider's configured model (`OPENAI_MODEL`, `OLLAMA_MODEL`, `MISTRAL_MODEL`), or the code constant `claude-sonnet-4-20250514` for Anthropic.
- `MediaAnalysisModelRegistry` builds the vision profile from the choice and returns the `whisper` profile for transcription; the static per-capability lists go.
- `MediaAnalysisGate::enabled()` reads `ai_config_bool('ai.features.media_analysis.enabled', false)`, like every other AI switch; it no longer depends on `PerModelSettingResolver`.
- `ProviderFactory::createOllama()` and `EmbeddingsProviderFactory::createOllama()` throw `ConfigurationException` when the URL is empty, as the key-based providers already do; their `localhost` fallbacks go.

**Translation.**

- `TranslationService` builds `DeepLTranslationService` when the choice is `deepl`, otherwise `AiTranslationService` with the chosen provider and model. No fallback service.
- A provider failure propagates. Today `performTranslation()` returns the source text on failure and `Cache::remember` keeps it for 30 days; the job then saves it as the translation, the locale counts as translated and is never retried.
- `TranslateModelJob` keeps translating the other locales, then rethrows the first failure so the queue retries (`tries = 3` with backoff). On retry, locales already translated are skipped by the existing `hasTranslation()` check.

**Whisper.** The provider name becomes `whisper`, matching `providers.whisper`. `WhisperTranscriber` already ignores the profile and posts to `providers.whisper.url`; its behaviour does not change.

## 5. Flows

**Refresh from the grid.** The administrator clicks the play icon on `features.chat.model`. `SettingActionRunner` renders `ai:models:refresh --setting="features.chat.model"` and runs it in the request. The command lists openai, ollama, mistral and anthropic where configured, filters for chat and tools, writes the choices and exits. The notification shows the output, including any provider error or a warning about the current value.

**Nightly.** The scheduler runs `ai:models:refresh`. Every model setting is refreshed with one call per provider. A failing provider keeps its entries and the command exits `1`, which the scheduler's failure handling surfaces.

**Choosing a model.** The administrator picks `anthropic:claude-sonnet-5` in the form. The next web request gets it from the overlay middleware; the next queued job gets it from the `JobProcessing` listener. Chat calls `ProviderFactory::make('anthropic', 'claude-sonnet-5')`.

**Translation failure.** DeepL returns an error for one locale of a content. Nothing is cached and nothing is saved for that locale; the other locales are translated; the job fails and retries later, skipping the locales already done.

## 6. Surfaces

- **Filament (`/admin`):** settings grid (row action, anomaly marker) and settings form (read-only action fields, searchable select, unavailable value).
- **Artisan:** `ai:models:refresh`, runnable by hand and scheduled.
- **CRUD API (`/app`, `/api/v1`):** `action_command` and `action_queued` are neither returned nor writable. `choices` is returned as today.

## 7. Testing

Core:

- Runner (unit): quoting and escaping, option form `--x={name}`, unknown placeholder, `{value}` on an encrypted setting, JSON and boolean values, unregistered command, sync result, queued dispatch (`Bus::fake`).
- Grid action (feature): visible only with `action_command` and the `update` permission; sync run executes the command and notifies; queued run dispatches; refusal notifies without running.
- Approval: a user who would otherwise be captured changes only `choices` and the change is written directly.
- Seeder: `action_command`/`action_queued` realigned on reseed; command-managed `choices` not overwritten on reseed while other rows' choices still are.
- Form and grid: value missing from the choices is offered as unavailable and flagged.
- Queue overlay: a job run after a setting changed in another process sees the new value without a worker restart.

AI:

- Listers (`Http::fake`): response parsing, Anthropic pagination, capability mapping per provider, OpenAI denylist, Ollama without `capabilities`, HTTP errors and timeouts.
- Catalog: not configured drops entries; failing keeps previous entries; capability filtering; unknown capabilities included; sorting; current-value report.
- Command: writes choices; `--setting`; unknown setting; failing provider gives exit `1` and keeps entries; warning for a value no longer offered.
- Runtime: each consumer uses the chosen provider and model; empty Ollama URL throws `ConfigurationException`.
- Translation: failure propagates and is not cached; the job rethrows after translating the other locales; `deepl` and an AI choice build the right service.
- Seeder: model settings created with `defaultChoice()` as initial value and choice, and with their action; `features.media_analysis.enabled` seeded off.
- Defaults: every feature resolves to `defaultChoice()` when no row exists, and that choice is on one of its providers.
- Media gate: off by default, on when the `features.media_analysis.enabled` setting is on.

## 8. Delivery order

Three repositories: `laraplate-core` (`Modules/Core`), `laraplate-ai` (`Modules/AI`), `laraplate` (this spec, the plan, submodule pointers).

1. Core: columns, model, seed definitions, `SettingActionRunner`, grid action, form and grid anomaly, `choices` approval exemption, queue overlay listener, removal of the two translation settings.
2. AI: Ollama clean-up; `AiModelFeature`, `AiModelChoice`, listers, `ModelCatalog`; settings, command and schedule; runtime wiring; media analysis (model settings, `whisper`, seeded master switch); translation.
3. Documentation in both modules.

## 9. Documentation

- Core: `Modules/Core/docs/rag/` user and developer pages for setting actions and command-managed choices; queue overlay behaviour in the developer page.
- AI: `Modules/AI/docs/rag/` user and developer pages for model selection and the refresh command; `Modules/AI/README.md` env table and settings paragraph (removed variables, `OLLAMA_API_URL` required for Ollama, `features.*.model` and `features.media_analysis.enabled` listed among the runtime settings); `Modules/AI/docs/WHISPER_INSTALLATION.md` (`whisper`, profile removal); `Modules/AI/docs/SEARCH_AND_TRANSLATION.md` (DeepL or AI in one setting, no fallback, retry on failure).

## 10. Out of scope, noted

- Embedding model selection (reindex implications; stays on its registry).
- OpenAI transcription (`whisper-1`) as a second transcription provider.
- `OPENAI_API_URL`: present in config, read by no factory.
- `OPENAI_MODEL`, `OLLAMA_MODEL`, `MISTRAL_MODEL` are shared by the chat factory fallback and the embeddings factory: `OPENAI_MODEL` defaults to `gpt-4o-mini` for one and `text-embedding-3-small` for the other, so setting it for either breaks the other. Once no feature reads them, they belong to embeddings; moving them to the embeddings profile registry is a separate change.
- User- or tenant-selectable provider (the TODO at the top of `Modules/AI/config/config.php`).
- `Modules/AI/docs/WHISPER_INSTALLATION.md` puts Whisper on port `8001`. The cross-encoder has no default address (`providers.cross_encoder.url` falls back to the `sentence_transformers` URL, and that service serves `/score`), so the two no longer conflict by default.
- Grouping the select options by provider.
