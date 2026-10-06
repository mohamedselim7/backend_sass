<?php

namespace App\Services\Ai\Prompts;

/**
 * Canonical prompt-engineering template used across every AI generation
 * surface (content, design idea, design image brief, reels, angles, chat).
 *
 * Every system prompt built through this class is assembled from the same
 * labelled sections, in the same fixed priority order, so reviewers and
 * tests can reason about "what the model was told" consistently:
 *
 *   1. SYSTEM/ROLE          — who the model is for this task
 *   2. TASK                 — exactly what must be produced
 *   3. CONTEXT              — brand/brief/background facts
 *   4. USER CONSTRAINTS     — explicit instructions from the user/brand,
 *                             ALWAYS rendered above the library defaults so
 *                             the model treats them as higher priority.
 *   5. OUTPUT CONTRACT      — the exact JSON/text shape to return
 *   6. QUALITY CRITERIA     — what "good" looks like (defaults)
 *   7. NEGATIVE CONSTRAINTS — what to never do
 *
 * Nothing the caller passes in is ever silently dropped: every non-empty
 * section is rendered, and `userConstraints` is namespaced as BINDING /
 * highest priority, explicitly above QUALITY CRITERIA (library defaults).
 */
final class PromptContract
{
    private string $role = '';

    private string $task = '';

    /** @var list<string> */
    private array $context = [];

    /** @var list<string> */
    private array $userConstraints = [];

    private string $outputContract = '';

    /** @var list<string> */
    private array $qualityCriteria = [];

    /** @var list<string> */
    private array $negativeConstraints = [];

    public static function make(): self
    {
        return new self;
    }

    public function role(string $role): self
    {
        $this->role = trim($role);

        return $this;
    }

    public function task(string $task): self
    {
        $this->task = trim($task);

        return $this;
    }

    public function context(string ...$lines): self
    {
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $this->context[] = trim($line);
            }
        }

        return $this;
    }

    /** Explicit user/brand instructions — rendered above defaults, marked BINDING. */
    public function userConstraints(string ...$lines): self
    {
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $this->userConstraints[] = trim($line);
            }
        }

        return $this;
    }

    public function outputContract(string $contract): self
    {
        $this->outputContract = trim($contract);

        return $this;
    }

    public function qualityCriteria(string ...$lines): self
    {
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $this->qualityCriteria[] = trim($line);
            }
        }

        return $this;
    }

    public function negativeConstraints(string ...$lines): self
    {
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $this->negativeConstraints[] = trim($line);
            }
        }

        return $this;
    }

    public function build(): string
    {
        $sections = [];

        if ($this->role !== '') {
            $sections[] = "SYSTEM/ROLE:\n{$this->role}";
        }
        if ($this->task !== '') {
            $sections[] = "TASK:\n{$this->task}";
        }
        if ($this->context !== []) {
            $sections[] = "CONTEXT:\n- ".implode("\n- ", $this->context);
        }
        if ($this->userConstraints !== []) {
            $sections[] = "USER CONSTRAINTS (explicit instructions from the user/brand — BINDING, highest priority, override any default below on conflict):\n- "
                .implode("\n- ", $this->userConstraints);
        }
        if ($this->outputContract !== '') {
            $sections[] = "OUTPUT CONTRACT (return exactly this shape, nothing else):\n{$this->outputContract}";
        }
        if ($this->qualityCriteria !== []) {
            $sections[] = "QUALITY CRITERIA (defaults — apply unless a USER CONSTRAINT above says otherwise):\n- "
                .implode("\n- ", $this->qualityCriteria);
        }
        if ($this->negativeConstraints !== []) {
            $sections[] = "NEGATIVE CONSTRAINTS (never do this):\n- ".implode("\n- ", $this->negativeConstraints);
        }

        return implode("\n\n", $sections);
    }
}
