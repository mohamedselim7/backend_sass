# AI prompts

All system prompts are built with `App\Services\Ai\Prompts\PromptContract`, always in this order (higher wins on conflict):

1. SYSTEM/ROLE
2. TASK
3. CONTEXT
4. USER CONSTRAINTS — the user's mandatory Planning & Understanding prompt. Never dropped; overrides defaults.
5. OUTPUT CONTRACT — exact JSON shape
6. QUALITY CRITERIA
7. NEGATIVE CONSTRAINTS

## Where
- Content plans: `ContentPrompts::system()`
- Design idea only: `ContentPrompts::designSystem()` (used by the dedicated design-idea endpoint)
- Image generation: `DesignFormat` + `config/design.php` (1:1 default 1024x1024; 4:5 and 9:16 ready).

## Versioning
`ContentPrompts::VERSION` is stored in `prompt_version` on generated rows. Bump it whenever wording changes so results can be compared.

## Tests
`tests/Unit/Ai/PromptContractTest.php` checks section order, that the mandatory prompt is kept and ranked above defaults, carousel rules and the square default.
