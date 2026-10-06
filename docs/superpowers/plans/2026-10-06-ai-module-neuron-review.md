---
status: open
created_on: 2026-10-06
---
# AI Module: Neuron Usage and Duplication Review — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Open. Tasks 1 to 10 are ready. Task 11 waits for four decisions of the owner and is not started.

**Goal:** `Modules/AI` uses the Neuron framework for what Neuron provides, repeats no code that can live once, and carries no code that nothing reaches. Behaviour changes only where a finding is a bug.

**Source:** three read-only reviews of the module, 2026-10-06: the use of Neuron in the calls to the model (findings A1-A9), retrieval, embeddings and evaluation (B1-B15), duplication and dead code (D1-D8, R1-R11). Finding ids in the steps point back to them. The reviews are not committed; every claim below was checked against the code when it was written, and a step that finds the claim false says so in its line and is not forced.

## Global Constraints

- **Neuron v3.17.0 is installed and is the source of truth.** The online documentation describes a newer retrieval API (`DocumentSchema`, `Filter`, `SearchRequest`, `store->delete(FilterGroup)`) that does not exist in `vendor/neuron-core/neuron-ai/src`. Do not plan against it. Read the vendor source and the docs through the Neuron MCP (`searchDocumentation`, `getPage`), and Laravel Boost `search-docs` for Laravel APIs, before writing code that depends on either. Do not write again what the framework offers: structured output, tools, middleware, chat history, testing fakes.
- No new dependencies and no new base folders. Neuron's own testing classes (`FakeAIProvider`, `FakeVectorStore`, `FakeEmbeddingsProvider`, `FakeMiddleware`) are already installed.
- Settings, not env: a feature switch or a tuning value is a seeded setting (`AIDatabaseSeeder`), read as `config('ai....')`. A task that finds an env variable that should be a setting records it; Task 11 decides.
- Never declare a class, trait, interface or enum inside a test file: stubs go in `Modules/AI/tests/Stubs/` (PSR-4 `Modules\AI\Tests\Stubs\`).
- Every PHP file `declare(strict_types=1);`, `#[Override]` where overriding, `final`/`readonly` where the siblings use them.
- Run tests from the `laraplate` root with `php artisan test --compact <path>`, never with a cached config. Format from the root, on an explicit file list: `vendor/bin/pint --format agent <files>`.
- Tick each step's checkbox when the step is done, in the same work block. A step dropped on purpose is `- [-]` with its reason on the same line.
- A deleted file, route, setting or env variable is searched across `Modules/*`, `config`, `routes`, `tests`, `docs` and `resources` first; the command and its result go in the commit message.
- Closing the plan needs a `**Documented in:**` line naming the module docs that describe the behaviour, and the docs of every removed or changed behaviour updated in the task that changes it.

---

### Task 1: Small fixes with no behaviour risk

Findings B4, B5, B14, B13(b-c), D7, R7 (path half), and the keys the code reads and nothing declares.

**Files:**
- Modify: `Modules/AI/app/Ai/Embeddings/EmbeddingsProviderFactory.php`
- Modify: `Modules/AI/app/Ai/Agents/DocumentationAgent.php`
- Modify: `Modules/AI/app/Ai/Agents/ChatAgent.php`
- Modify: `Modules/AI/app/Services/DocumentationService.php`
- Modify: `Modules/AI/config/config.php`
- Modify: `Modules/AI/README.md`
- Delete: `Modules/AI/app/Ai/MediaAnalysis/Transcription/NullMediaTranscriber.php`
- Delete: `Modules/AI/tests/Stubs/TranslatableTestModelA.php`, `Modules/AI/tests/Stubs/TranslatableTestModelB.php`, `Modules/AI/tests/Stubs/TranslatableMissingTestModelA.php`, `Modules/AI/tests/Stubs/TranslatableMissingTestModelB.php`, `Modules/AI/tests/Stubs/TranslatableMissingTestModelTranslation.php`
- Test: `Modules/AI/tests/Integration/EmbeddingsProviderFactoryTest.php`, `Modules/AI/tests/Integration/DocumentationAgentTest.php`

- [ ] **Step 1: Dimensions of OpenAI and Voyage embeddings (B4).** Neuron's `OpenAIEmbeddingsProvider` defaults to 1024 dimensions and sends them on every call, so a profile of 1536 gets vectors of 1024; `VoyageEmbeddingsProvider` takes them as its third argument. Pass the profile's `dimensions` when the provider is built for a profile. Test, with the reflection style the file already uses, that the dimensions reach both providers.

- [ ] **Step 2: One path for the filesystem vector store (B5, R7).** `DocumentationAgent` builds a `FileVectorStore` that appends `.store`; `DocumentationService` checks, sizes and unlinks the configured path as it is, and the suffix of the profile is derived twice (`DocumentationAgent::getStorePath`/`profiledStorePath`, `DocumentationService::getFilesystemVectorStoreFilePath`). Put the derivation in one class both use, pass the extension to `FileVectorStore`, and make `isAvailable()` and the `--full` unlink act on the file that the store writes. Test a filesystem case: after indexing, `isAvailable()` is true and `--full` removes that file. Reconcile the default of `ai.features.faq.vector_store` (the code says `filesystem`, `config.php` says `elasticsearch`) and the comment of the path in `config.php`.

- [ ] **Step 3: Remove the static constructors that the framework already gives (B14).** `ChatAgent::make()` and `DocumentationAgent::make()` copy `NeuronAI\StaticConstructor`, which the Neuron workflow uses. Delete them; keep `ChatAgent::forFeature()`. Run `ChatAgentTest` and `DocumentationAgentTest`.

- [ ] **Step 4: Keys read and never declared.** `ai.features.application_content.timeout_seconds` (`ApplicationContentToolProvider`), `ai.features.embeddings.retry_until_minutes` (`GenerateEmbeddingsJob`, documented in `SEARCH_AND_TRANSLATION.md`), `ai.features.chat.summary_threshold` (`MemoryService`) and `ai.vendor` (`rag_paths()`). Declare each in `config.php` with its present default, or inline the default and drop the read of the key. Fix the comment of `GenerateEmbeddingsJob` that says 180 seconds while the value is 300.

- [ ] **Step 5: Dead provider, keys and stubs (D7).** Delete `NullMediaTranscriber` (the provider binds `WhisperTranscriber`, which is already a no-op without its URL), `features.faq.elasticsearch.index` with `AI_FAQ_ES_INDEX` (`.env.example`, README), `providers.openai.api_url` with `OPENAI_API_URL`, `providers.deepl.api_key` (the DeepL service reads `core.deepl_api_key`) and the five unused stubs. For each, run the repo-wide search first; a key that something reads stays.

- [ ] **Step 6: Format, run the AI embeddings, documentation and agent tests, commit.** `fix(ai): ...`.

---

### Task 2: One service graph for the assistant tests

Finding R6.

**Files:**
- Modify: `Modules/AI/tests/Stubs/Assistance/ScriptedAssistantFixtures.php`
- Create: `Modules/AI/tests/Stubs/Assistance/NoToolsProvider.php`
- Modify: `Modules/AI/tests/Feature/InAppAssistanceSecurityTest.php`
- Modify: `Modules/AI/tests/Feature/Assistance/ConversationTitleTest.php`
- Modify: `Modules/AI/tests/Feature/Assistance/AssistantScopeRespondTest.php`
- Modify: `Modules/AI/tests/Feature/Assistance/UiProposalsTest.php`
- Modify: `Modules/AI/tests/Feature/Assistance/AgentEndpointTest.php`

- [ ] **Step 1: The helper.** Add `ScriptedAssistantFixtures::inAppService(Request, ?Closure $retrieve, Closure $complete, ?AssistantPolicyCompiler $compiler = null, ?ChatService $chat = null)` building the `InAppAssistanceService` with the real collaborators, and `NoToolsProvider`, a `ContextualToolProviderInterface` that offers no tool. It replaces the six pasted constructions and the `Mockery::mock(ContextualToolProviderInterface::class)` with `shouldReceive('tools')` repeated six times; the anonymous classes of `ApplicationContentToolProviderTest` and `InAppApplicationContentAssistanceTest` become `NoToolsProvider` too when they fit.

- [ ] **Step 2: Move the five test files to it.** Each file keeps a one-line wrapper only where its name is part of the test's vocabulary. Run each file alone, then together, after every file: a helper defined twice in one Pest process is a fatal error.

- [ ] **Step 3: Format, run the assistant suites, commit.** `test(ai): one construction of the in-app service for the assistant tests`.

---

### Task 3: Shared code of the evaluation harnesses

Findings R1, R2, R3, B6, B7, B12. Neuron's Evaluation module is not a replacement: it is pass or fail per item and has no aggregate metric, slice, percentile or committed baseline (B8). The IR harness stays ours.

**Files:**
- Create: `Modules/AI/app/Services/Evaluation/EvaluationStatistics.php`
- Create: `Modules/AI/app/Services/Evaluation/EvaluationDatasetReader.php`
- Create: `Modules/AI/app/Console/Concerns/WritesJsonReport.php`
- Modify: `Modules/AI/app/Services/ApplicationContent/Evaluation/IrMetrics.php`
- Modify: `Modules/AI/app/Services/Documentation/Evaluation/DocumentationEvaluationService.php`
- Modify: `Modules/AI/app/Services/ApplicationContent/Evaluation/ApplicationContentEvaluationService.php`
- Modify: `Modules/AI/app/Services/ApplicationContent/Evaluation/ApplicationContentRetrievalStrategyEvaluationService.php`
- Modify: `Modules/AI/app/Services/Assistance/Evaluation/AssistantEvaluationService.php`
- Modify: `Modules/AI/app/Services/Documentation/Evaluation/DocumentationEvaluationDataset.php`
- Modify: `Modules/AI/app/Services/ApplicationContent/Evaluation/ApplicationContentEvaluationDataset.php`
- Modify: `Modules/AI/app/Services/Assistance/Evaluation/AssistantEvaluationDataset.php`
- Modify: `Modules/AI/app/Console/EvaluateDocumentationCommand.php`
- Modify: `Modules/AI/app/Console/EvaluateApplicationContentCommand.php`
- Modify: `Modules/AI/app/Console/EvaluateApplicationContentRetrievalStrategiesCommand.php`
- Modify: `Modules/AI/app/Console/TuneRetrievalCommand.php`
- Test: the `*EvaluationServiceTest`, `*EvaluationDatasetTest`, `*EvaluationCaseTest`, `IrMetricsTest` files, the three baseline gates and the four command tests

- [ ] **Step 1: Characterize before moving.** Run the evaluation tests and the three baseline gates (`DocumentationBaselineGateTest`, `DeveloperDocumentationBaselineGateTest`, the CMS and SAO application-content baselines) and keep their output: the reports pin rounded values, and no committed report under `docs/rag/evaluations` may change.

- [ ] **Step 2: `EvaluationStatistics` (R2, B6).** `ratio`, `rounded`, `percentile`, `latency` and `groupBySlices(records, metricsFn)`, lifted verbatim from the four services (their copies are identical; the strategy service adds an empty-records guard, which the class keeps). Add `IrMetrics::firstRank()` and `reciprocalRank()` for the loops that compute the first relevant rank and the reciprocal rank in four places (`DocumentationEvaluationService`, `ApplicationContentEvaluationService`, the strategy service, `RetrievalTuningService`). The services keep their own domain metrics. `RetrievalTuningService::ratio()` goes too.

- [ ] **Step 3: `EvaluationDatasetReader` (R1, B7).** The reading of a dataset file (2,000,000 bytes, depth 32, at most 1000 cases, a list of cases), `assertExactKeys`, `string`, `integer`, `boolean`, `stringList` and `isValidRevision`, parameterised by the label that the exception messages carry so that they stay the same. The assistant dataset keeps its own optional-key helpers. The application-content dataset keeps `assertOutsideProject`. Neuron's `JsonDataset` only decodes, with no schema, size cap or location check, so it cannot replace this. The repeated checks of the three case classes (id and locale patterns, the 2000 character query, `validStringList`, `validSlugList`) become one small shared class.

- [ ] **Step 4: `WritesJsonReport` (R3, B12).** `optionString()`, and `writeReport(...)` doing the checks and the write the four commands repeat: the output exists and `--force` is absent, the directory is not writable, one `json_encode` with the same flags, a temporary file moved into place and removed in a `finally`. The profile parsing of `IndexDocumentationCommand` and `CreateRagElasticsearchIndexCommand` becomes one concern.

- [ ] **Step 5: The same output.** Rerun Step 1 and compare: every report and every message is identical. Format, commit. `refactor(ai): one copy of the evaluation statistics, the dataset reader and the report writer`.

---

### Task 4: Real citations and one retrieval for the documentation agent

Findings B1, B3, B10, B11. All three are bugs, not duplication.

**Files:**
- Create: `Modules/AI/app/Ai/Rag/RecordingPostProcessor.php`
- Modify: `Modules/AI/app/Ai/Agents/DocumentationAgent.php`
- Modify: `Modules/AI/app/Services/DocumentationService.php`
- Modify: `Modules/AI/app/Ai/Rag/Retrieval/DeveloperDocumentationRetrieval.php`
- Modify: `Modules/AI/app/Ai/Rag/Retrieval/InAppDocumentationRetrieval.php`
- Modify: `Modules/AI/app/Console/LaraplateHelpCommand.php`
- Test: `Modules/AI/tests/Integration/DocumentationServiceTest.php`, `Modules/AI/tests/Unit/Ai/Rag/Retrieval/`, `Modules/AI/tests/Feature/DeveloperDocumentationBaselineGateTest.php`

- [ ] **Step 1: Prove the bug with a real agent (B1, B10).** `DocumentationServiceTest` mocks the concrete `DocumentationAgent`, a `Mockery` handler and a `Message` that is given `getCitations()`; in Neuron 3.17 neither `Message` nor `AssistantMessage` has that method, and the RAG flow never sets citations, so `buildCitations()` is dead and `ai:help` never prints sources. Write the failing test with a real `DocumentationAgent` on `FakeVectorStore`, `FakeEmbeddingsProvider` and `FakeAIProvider` (`setVectorStore`, `setEmbeddingsProvider`, `setAiProvider`): asking a question must give the retrieved documents as sources. Keep the existing assertion that a source is never split across two batches, from the order that `FakeVectorStore` records.

- [ ] **Step 2: Citations from the documents.** `RecordingPostProcessor` is a Neuron `PostProcessorInterface` (`vendor/neuron-core/neuron-ai/src/RAG/PostProcessor/PostProcessorInterface.php`) that keeps the documents that reach the model; `DocumentationAgent::postProcessors()` returns it, and `DocumentationService` builds the citations from those documents (source name, excerpt, score). Delete the `method_exists($message, 'getCitations')` branch.

- [ ] **Step 3: One retrieval for evaluation and production (B3, B11).** `ai:evaluate-documentation --index=developer` measures `DeveloperDocumentationRetrieval` (query embedding, prefix, `min_similarity`, fail-fast); `ai:help` runs the default `SimilarityRetrieval` with no threshold. Give `DocumentationAgent::postProcessors()` Neuron's `FixedThresholdPostProcessor` on the `min_similarity` setting, so the agent path applies the same filter, and reduce the two retrieval classes to the same embedding and query handling plus that processor. The duplicated `aboveMinimumSimilarity` goes, and so does the extra Elasticsearch count that `hasDocuments()` makes on every query where the result is already known. Keep the fail-fast behaviour and the generic exception message (no leak).

- [ ] **Step 4: Run the baseline gates.** The retrieval of the in-app and developer indexes must not change its ranking; the answers of `ai:help` become stricter by the threshold, and the plan records it. Format, commit. `fix(ai): documentation answers cite the documents they used and apply the retrieval that is evaluated`.

---

### Task 5: Passage prefix on the documentation index

Finding B2. Behaviour changes: relevance shifts, and the documentation indexes must be rebuilt.

**Files:**
- Create: `Modules/AI/app/Ai/Embeddings/PrefixingEmbeddingsProvider.php`
- Modify: `Modules/AI/app/Ai/Embeddings/EmbeddingsProviderFactory.php`
- Modify: `Modules/AI/app/Services/EmbeddingService.php`
- Modify: `Modules/AI/app/Ai/Rag/Retrieval/InAppDocumentationRetrieval.php`
- Modify: `Modules/AI/app/Ai/Rag/Retrieval/DeveloperDocumentationRetrieval.php`
- Modify: `Modules/AI/app/Services/SearchEmbedder.php`
- Test: `Modules/AI/tests/Unit/Embeddings/EmbeddingPrefixTest.php`, `Modules/AI/tests/Unit/Ai/Rag/Retrieval/`

- [ ] **Step 1: The failing test.** With the first profile (`intfloat/multilingual-e5-small`), the documentation chunks reach the provider without `passage: ` while the in-app and developer queries are embedded as `query: ...`. Assert with `FakeEmbeddingsProvider::assertEmbeddedText('passage: ...')` that indexing a documentation chunk embeds the prefixed text, and that the stored `Document` content is not changed (it is shown to the model).

- [ ] **Step 2: The decorator.** `PrefixingEmbeddingsProvider` wraps the provider that `EmbeddingsProviderFactory::make()` returns: `embedText()` prepends the query prefix, `embedDocument()` and `embedDocuments()` embed a prefixed copy and copy the vector back, without touching `Document::content`. Neuron's `EmbeddingsProviderInterface` has no query and passage split, so a decorator is the extension point. Then delete the manual prefixing in `SearchEmbedder`, the two retrievals and `EmbeddingService`, which mutates `$chunk->content` today. A site missed means a double prefix: search the repo for `query: ` and `passage: ` and account for each hit.

- [ ] **Step 3: Rebuild and measure.** Rebuild the documentation indexes (`ai:index-rag-docs --full`, or a model switch) and run the two documentation baselines. The baseline stub strips prefixes and hashes, so it cannot see this change; say so in the delivery status, and that the relevance gain is measured by `ai:evaluate-documentation` against a live Elasticsearch and embeddings service, which is manual.

- [ ] **Step 4: Document the rollout.** `Modules/AI/docs/rag/MODULE.md` says that the documentation indexes must be rebuilt after this change. Format, commit. `fix(ai): documentation chunks are embedded as passages, through one embeddings decorator`.

---

### Task 6: Neuron structured output where the code parses model JSON by hand

Findings A1, B9, D3 (the helper half).

**Files:**
- Create: `Modules/AI/app/Data/ModerationVerdictData.php`, `ExtractedFacts.php`, `ImageAnalysisData.php`, `SearchPlanData.php`, `InjectionVerdict.php`
- Modify: `Modules/AI/app/Services/ModerationService.php`
- Modify: `Modules/AI/app/Services/LlmSearchService.php`
- Modify: `Modules/AI/app/Services/SearchOrchestratorAgent.php`
- Modify: `Modules/AI/app/Ai/MediaAnalysis/Vision/NeuronVisionAnalyzer.php`
- Modify: `Modules/AI/app/Services/MemoryService.php`
- Modify: `Modules/AI/app/Services/GuardrailsService.php`
- Modify: `Modules/AI/config/config.php`
- Test: `Modules/AI/tests/Integration/Services/ModerationServiceTest.php`, `Modules/AI/tests/Integration/LlmSearchServiceTest.php`, `Modules/AI/tests/Integration/MemoryServiceFullTest.php`, `Modules/AI/tests/Integration/GuardrailsServiceFullTest.php`

The model to copy is `ConversationTitleService` with `GeneratedConversationTitle`, and its test `ConversationTitleServiceTest`, which asserts the class and the schema sent with `FakeAIProvider`.

- [ ] **Step 1: Moderation.** `ModerationVerdictData` (a backed enum for the verdict, `confidence` from 0 to 1, categories, reason, the auto-approve flag) with `#[SchemaProperty]` and Neuron's validation rules; `ModerationService::analyze()` calls `structured(..., maxRetries)`. Delete the markdown fence regex, `json_decode`, `stringValue`, `floatValue`, `boolValue`, `stringListValue`, `mapResponse`, the hand-written retry that appends "Respond with valid JSON only", and `GuardrailsService::validateJsonOutput`. The existing `catch (Throwable)` that answers `Uncertain` stays, so a verdict that never validates ends as before, at one retry. The prompt is owned by CMS and already carries the JSON shape; `structured()` only adds the output constraint, so `CommentModerationPrompt` is not touched. Rework the two tests that call `mapResponse` directly onto `FakeAIProvider`.

- [ ] **Step 2: Search plan.** `SearchPlanData` for the plan that `LlmSearchService` asks for; delete `parseJsonResponse` and the coercion helpers (`stringListValue`, `arrayValue`, `expandedQueryValue`). `SearchOrchestratorAgent::sanitizePlan()` keeps its clamping, which is domain defence, and drops only the type coercion. The plan is cached for 600 seconds: keep the key stable. Give `LlmSearchService` the agent seam that `ConversationTitleService` has, and write the first tests that drive it with `FakeAIProvider` (today only its failure path is tested).

- [ ] **Step 3: Vision and memory.** `ImageAnalysisData` for `NeuronVisionAnalyzer` (delete the "STRICT JSON" prompt wording, `withoutCodeFence` and `json_decode`); a failure still throws, so `AnalyzeMediaJob` retries as before. `ExtractedFacts` for `MemoryService::extractFacts()`, which swallows any parse failure and returns `[]` with no retry. `MemoryService` is dormant (Task 11), and the plan of persistent user memory reuses it, so this makes that plan start from a structured call.

- [ ] **Step 4: The injection verdict.** `InjectionVerdict` (an enum: safe or unsafe) replaces the `str_contains` and `preg_replace` parsing of the LLM fallback in `GuardrailsService`. Whether that path is wired into the assistant or removed is Task 11; this step only stops it parsing by hand.

- [ ] **Step 5: Config.** Remove `ai.features.guardrails.retry_on_failure` (`AI_GUARDRAILS_RETRY`) and the hand-written retry it controlled: `maxRetries` of `structured()` is the knob now. Record it in the module docs.

- [ ] **Step 6: Format, run the four test files and the moderation listeners tests, commit.** `refactor(ai): the model's JSON goes through Neuron's structured output`.

---

### Task 7: Tool failures that do not refuse the whole turn

Finding A3. The guardrail on tool results (A2) is part of Task 11, because it needs the decision on the safety classifier.

**Files:**
- Modify: `Modules/AI/app/Ai/Agents/ChatAgent.php`
- Modify: `Modules/AI/app/Services/Tools/ToolDefinition.php`
- Modify: `Modules/AI/app/Services/Tools/ToolRegistry.php`
- Modify: `Modules/AI/app/Services/Tools/GraphToolProvider.php`
- Modify: `Modules/AI/app/Services/Tools/CrudToolProvider.php`
- Test: `Modules/AI/tests/Integration/ToolRegistryTest.php`, `Modules/AI/tests/Feature/Assistance/AgentEndpointTest.php`

- [ ] **Step 1: The failing test.** A model that omits a required argument makes `Tool::execute()` throw `MissingCallbackParameter`; `ToolNode` rethrows it without a handler, and `respond()` turns the whole turn into the canned refusal. Drive it with `ToolCallingFakeProvider` (a call with a missing argument, then a normal answer): the turn must complete.

- [ ] **Step 2: `toolErrorHandler`.** `ChatAgent` gives Neuron's `toolErrorHandler()` a handler that answers the model with a fixed message that never echoes the exception text, so the model can try again.

- [ ] **Step 3: `toolMaxRuns`.** `ToolDefinition` gains an optional `maxRuns`, which `ToolRegistry::buildNeuronToolStructure()` sets with `Tool::setMaxRuns()`; the expensive tools (`graph_*`, `crud_*`) declare a small limit. `ToolRunsExceededException` after the default 10 runs also ends in the canned refusal today.

- [ ] **Step 4: Format, run the tool and assistant suites, commit.** `fix(ai): a tool that fails or runs too often does not refuse the whole turn`.

---

### Task 8: One way to build an agent, and the tests on Neuron's fakes

Findings A4, A5, B10, R9. After Task 6, the list of services that build an agent is shorter.

**Files:**
- Modify: `Modules/AI/app/Ai/Agents/ChatAgent.php`
- Modify: `Modules/AI/app/Services/MemoryService.php`
- Modify: `Modules/AI/app/Services/ModerationService.php`
- Modify: `Modules/AI/app/Services/GuardrailsService.php`
- Modify: `Modules/AI/app/Services/ContextualSuggestionService.php`
- Modify: `Modules/AI/app/Services/Translation/AiTranslationService.php`
- Modify: `Modules/AI/app/Listeners/HandleAiTextGenerationListener.php`
- Modify: `Modules/AI/app/Services/Assistance/ConversationTitleService.php`
- Test: the seven test files that mock `ChatAgent`

- [ ] **Step 1: Decide the seam by what is left.** Each service carries a nullable `Closure $chatAgentFactory` and a private `makeChatAgent()` with an inline `@codeCoverageIgnore`, and the same `chat(new UserMessage(x))->getMessage()->getContent() ?? ''` plus `mb_trim`. Add `ChatAgent::ask(string $prompt): string` for the plain text one-shots. For the seam, prefer Neuron's own: a test builds the agent with `setAiProvider(new FakeAIProvider(...))`, so one container-resolvable factory per `AiModelFeature` that tests swap replaces the closure argument. If that proves heavier than the closures it replaces for a service, the step says so for that service and leaves it.

- [ ] **Step 2: Move the services,** one commit each, running that service's tests: the constructor signatures change, all the call sites are in the module and resolved by the container.

- [ ] **Step 3: The tests.** Replace the `Mockery::mock(ChatAgent) -> chat() -> Mockery::mock(AgentHandler) -> getMessage()` chain (about 58 times in `GuardrailsServiceFullTest`, `MemoryServiceFullTest`, `HandleAiTextGenerationListenerTest`, `AiTranslationServiceTest`, `ContextualSuggestionServiceTest`, `DocumentationServiceTest`, `ModerationServiceTest`) with `new FakeAIProvider(new AssistantMessage(...))`, which also lets each test assert the system prompt and the messages the model was given. `FakeEmbeddingsProvider` is limited to 32 components, so the tests that need vectors of 384 or 1024 keep a stub from `tests/Stubs/Embeddings`; do not force the fake there. `AgentRoundTripTest` uses `FakeVectorStore`.

- [ ] **Step 4: Overlapping test files.** `GuardrailsServiceTest`/`GuardrailsServiceFullTest`, `MemoryServiceTest`/`MemoryServiceFullTest`, `ConversationModelTest`/`ConversationModelExtendedTest`, `EmbeddingServiceTest`/`EmbeddingServiceFullTest` test the same classes twice; merge the repeated cases. Move the anonymous `new class` declarations that the `AGENTS.md` rule forbids in test files to `tests/Stubs`.

- [ ] **Step 5: Format, run the AI suite, commit.** `refactor(ai): agents are built in one place and the tests use Neuron's fakes`.

---

### Task 9: One gate for embeddings and for translation

Findings R4, R5.

**Files:**
- Create: `Modules/AI/app/Services/EmbeddingsGate.php`, `Modules/AI/app/Services/TranslationGate.php`
- Modify: `Modules/AI/app/Listeners/HandleModelIndexingListener.php`, `Modules/AI/app/Listeners/HandleBulkModelIndexingListener.php`, `Modules/AI/app/Listeners/HandleTranslationReembeddingListener.php`, `Modules/AI/app/Listeners/HandleModelTranslationListener.php`, `Modules/AI/app/Listeners/HandleModificationApprovedTranslationListener.php`
- Modify: `Modules/AI/app/Services/EmbeddableModels.php`
- Modify: `Modules/AI/app/Console/RepairMissingEmbeddingsCommand.php`
- Test: the `Handle*ListenerTest` files, `RepairMissingEmbeddingsCommandTest`, `FeatureModuleGateTest`

- [ ] **Step 1: The gap first.** `HandleModificationApprovedTranslationListener` checks the flag and the settings and skips `FeatureModuleGate`, so an approved modification may translate in a module that the allowlist excludes; `isTranslatable` is implemented twice with `class_uses_trait` and `class_uses_recursive`; the indexing listeners read `config('ai.features.embeddings.enabled', true)` while the seeded default is `false`. Write a failing test for each, and check against the code that each is real before changing it.

- [ ] **Step 2: The gates.** `EmbeddingsGate::allows(Model, bool $requireEmbeddable = true)` (the flag, `FeatureModuleGate`, the `Searchable` trait, `isEmbeddable`) and `TranslationGate::allows(Model)`, after the pattern of `MediaAnalysisGate`. The five embeddings copies and the two translation copies use them. The behaviour of the listeners that a module excludes is the same; the one that skipped the gate now applies it, which the delivery status records.

- [ ] **Step 3: The cache event key (R5).** The key `model_indexing:{table}:{key}` with a ten minute `Cache::put` is written in three AI listeners and read in Core (`FinalizeModelIndexingListener`, `Searchable`); the moderation flavour is in two more. Put `cacheKey()` and a TTL constant on the Core events and call them from both modules. This touches `Modules/Core`: it is a separate commit in that submodule, and the key string stays byte for byte the same.

- [ ] **Step 4: Format, run the listener, repair-command and Core indexing tests, commit.**

---

### Task 10: Dead code whose removal needs no decision

Findings D2, D5, D6, D8 (the half that does not depend on Task 11).

**Files:**
- Modify: `Modules/AI/app/Http/Controllers/ChatController.php`
- Modify: `Modules/AI/routes/web.php`
- Modify: `Modules/AI/resources/swagger/AI-swagger.json`
- Modify: `Modules/AI/app/Models/Message.php`, `Modules/AI/app/Models/Conversation.php`
- Modify: `Modules/AI/app/Services/Assistance/AssistantAccessContextFactory.php`
- Delete: `Modules/AI/app/Actions/IntelligentSearchAction.php`, `Modules/AI/tests/Integration/IntelligentSearchActionTest.php`
- Modify: `Modules/AI/docs/GLOSSARY.md`, `Modules/AI/docs/rag/GLOSSARY.md`, `Modules/AI/docs/rag/ASSISTANT_SCOPE.md`

- [ ] **Step 1: The duplicated message route (D2).** `ChatController::sendMessageWithTools` is `insertMessage` wrapped with `action_requests: []`, always empty, and its route `ai.crud.messages.with-tools` is live while the glossaries call the method removed. Delete the method, the route, the swagger entry and its two tests (`ChatControllerTest`), and remove `messages-with-tools` from `ResolveAssistantApplicationContextTest` and from `ASSISTANT_SCOPE.md`. Search `laraplate-ui` and `laraplate-importers` for a client first.

- [ ] **Step 2: Methods only tests call (D5).** `Message::byRole`, `Conversation::getMessagesForNeuron` (a hand-written message mapper; Neuron's `ChatHistory` would be the way if history is ever replayed) and `AssistantAccessContextFactory::forDeveloperHelp` (`LaraplateHelpCommand` never calls it). For each, search first and delete it with its tests, or keep it and say in the step why.

- [ ] **Step 3: `IntelligentSearchAction` (D6).** It calls `EnsembleSearchService::search()` with a signature that no longer exists and nothing calls it. Its removal is Task 4 Step 5 of `2026-09-16-search-modes-and-strategy-resolution.md`, which first salvages `evaluateResults` and `shouldRetry` into Core's `SearchQualityEvaluator`: do it only when that plan reaches the step, or do the salvage here and tick it there. Then delete the file, its test, its entries in `phpstan-baseline.neon` and the empty `app/Actions` folder.

- [ ] **Step 4: Docs that describe code that is gone (D8).** `docs/GLOSSARY.md` and `docs/rag/GLOSSARY.md` say `ChatService` orchestrates streaming and tool calls and that `streamMessage` is the primary SSE path (it answers 422, and `ChatService` has two methods); the README says in-app chat is `ChatService` with question detection and lists "Streaming responses (SSE)". Correct them. The long superseded sections of `ARCHITECTURE.md` and `DESIGN_DECISIONS.md` wait for Task 11, which decides what stays.

- [ ] **Step 5: Format, run the chat, assistant and search tests, commit.**

---

### Task 11: Decisions of the owner

None of these has an obvious answer from the code. Each step asks for one decision, with what the review found; the work that follows goes in a plan of its own or in an amended step here.

- [ ] **Step 1: The `ActionRequest` approval flow (D1, A6).** `ActionRequestService`, `RiskClassifier`, `ActionRequestController` and its five routes, `ApproveActionRequest`/`RejectActionRequest`, `ExecuteActionRequestJob`, the model and table `ai_action_requests`, the exceptions, and the global registry of `ToolRegistry` (`register`, `getTool`, `getAllTools`, `hasTools`, `getAllNeuronTools`, `getAllNeuronToolsWithApproval`) are reached by nothing but their own tests: about 690 production lines and about 1,000 test lines. Retire it (delete it all, with a drop-table migration, and keep `ToolRegistry`'s mapping from definition to tool), or reconnect it on Neuron's `ToolApproval` middleware (`src/Agent/Middleware/ToolApproval.php`, with interrupt and resume) using `RiskClassifier` as the condition. Spec `2026-09-16-in-app-assistant-tools-design.md` and plan `in-app-assistant-tools` depend on the answer.

- [ ] **Step 2: Guardrails (D3, A2).** `GuardrailsService::checkPromptInjection` and its Lakera and LLM fallback are called by nobody, so `ai.features.guardrails.prompt_injection_detection` and `LAKERA_API_KEY` do nothing for the assistant, which uses the deterministic regex classifier; the config keys `guardrails.enabled` and `guardrails.json_validation` and `tools.enabled` have no reader. And what a tool returns to the model during the loop is not re-validated: `ApplicationContentToolProvider` calls the guardrail, `CrudToolProvider` and `GraphToolProvider` do not, so rows written by users reach the model as they are. Decide: implement `AssistanceSafetyClassifierInterface` with a small structured `InjectionVerdict` agent behind a setting, and add a Neuron `WorkflowMiddleware` on `ToolNode` that checks every tool result (rollout behind a flag, evaluated with `InAppAssistanceSecurityTest` data, since the regexes may flag legitimate data); or delete the dead path with its keys, env variables and tests. The CMS and SAO specs say "reuse `GuardrailsService`", so check them first.

- [ ] **Step 3: `MemoryService` and the summaries (D4, A7).** Dormant, wired nowhere; the plan `persistent-user-memory` reuses its `extractFacts`, which Task 6 turns into a structured call. If that plan is dropped, `MemoryService`, `ConversationSummary`, `features.chat.summary.enabled` and their tests go; if it stays, Neuron's `Summarization` middleware and a chat history over `Message` are the framework way, and the assistant stays stateless until a security review accepts replaying history.

- [ ] **Step 4: Env variables for switches and tuning (R11).** `AI_TEXT_GENERATION_*`, `AI_SEARCH_ORCHESTRATION_ENABLED`, `AI_FAQ_VECTOR_STORE`, `AI_FAQ_POLICY_CLASSIFICATION_VERSION`, `AI_MODERATION_QUEUE` are read from env through `config.php`, against the rule that switches and tuning values are seeded settings. The embeddings chunk and lock values already say they are deliberately not settings. Move them to seeded settings, or record the exception.

- [ ] **Step 5: Do the work that the answers allow,** in a plan of its own. This includes the removal of the superseded chat sections of `ARCHITECTURE.md` and `DESIGN_DECISIONS.md`, `TOOLS_USAGE_EXAMPLE.md` and the env variables that the docs name and nothing reads (`AI_EMBEDDINGS_ENABLED`, `AI_CHAT_ENABLE_SUMMARY`, `AI_MODERATION_*`, `AI_COMMENT_*`, `AI_TOOLS_ENABLED`, `AI_GUARDRAILS_ENABLED`), rewritten as "setting X in Filament".

---

### Task 12: Close

- [ ] **Step 1: Run the whole AI suite and the baseline gates,** and the CMS and SAO application-content baselines, from the `laraplate` root.

- [ ] **Step 2: Delivery status and documentation.** Add the `## Delivery status (date): ...` section with the divergences and what Task 11 left open, and the `**Documented in:**` line. The module docs named in it describe each changed behaviour: `Modules/AI/docs/rag/MODULE.md` (citations, the retrieval and the prefix, the rebuild of the indexes), `DOCUMENTATION_EVALUATION_DEVELOPER.md` (the shared evaluation code), `ASSISTANT_EVALUATION.md`, `Modules/AI/README.md` (removed env variables).

- [ ] **Step 3: Record what is not measured.** The effect of Task 4 and Task 5 on relevance needs a live Elasticsearch and embeddings service and is measured by hand with `ai:evaluate-documentation`; the plan says so rather than claiming a gain.
