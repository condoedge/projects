# Effort / time estimation method (Claude Code, no live API)

The module does **not** call an LLM API. Estimation is a human-in-the-loop step you run in
**Claude Code**, then record on the feature request. This keeps the module dependency-free and
lets you use whatever model/context you have open.

## Procedure

1. Open the feature request and make sure it is well documented: **problem**, **proposed
   solution**, **application reference** (module / route / screen), **acceptance criteria**,
   **menu design**, and screenshots.
2. In the feature-requests table, use **"Export for AI estimation"** — it dumps the full
   documentation as markdown.
3. Paste that markdown into Claude Code with the prompt below.
4. Copy the returned numbers back into the feature request: **Effort (days)**, **Complexity**,
   **Confidence**, and paste the rationale into **Estimation notes**. Set status to *Estimated*.

## Prompt template

```
You are estimating implementation effort for a change to the SISC Laravel 10 + Kompo codebase.
Here is the fully documented feature/change request:

<PASTE THE EXPORTED MARKDOWN HERE>

Return, as a short table:
- Effort in ideal engineering days (0.5-day granularity)
- Complexity: trivial | simple | moderate | complex | very complex
- Confidence: low | medium | high
- A 3-6 bullet rationale: the files/modules likely touched, main risks, and any unknowns that
  would change the estimate. Call out anything that should be split into separate tasks.
```

## Notes

- Prefer estimating **after** the acceptance criteria are written — vague criteria produce vague
  estimates (reflect that in a *low* confidence).
- If Claude suggests splitting the work, create the tasks in the **Tasks** tab and set their
  precedence (dependencies) so the Gantt reflects the real order.
- Re-estimate when scope changes; the notes field is the audit trail.
