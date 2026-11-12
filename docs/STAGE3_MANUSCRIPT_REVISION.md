# Stage 3 documentation and manuscript revision

12 November 2026. Scope: enabled torture-case monitoring, external grants,
Unknown review, affected field definitions, workflow figures and technical claims.
No application behavior, database records or deployment was changed in Stage 3.

## Updated material

- `STOP_AND_ASK_SUMMARY.md` is explicitly superseded; its original blocked-state
  description and old test result remain as history.
- [Current behavior and evidence](TORTURE_DEADLINE_STATUS.md) documents the
  60/90-day rule, stored override behavior, grant history, classification review,
  unchanged interchange contracts and outstanding acceptance.
- The authorized vault status/decision pages and sprint/manuscript checklists
  now have dated superseding notices. Older results were preserved. No session
  note was written, branch state invented or approval response fabricated.
- The existing [live manuscript](https://docs.google.com/document/d/127VZN6tvbm6bAbPc_mGW2_Xy0xFU8nY6uB_eBwXxXoE/edit)
  was edited in place with revision guards. The target was refreshed before
  editing; the July PDF and August local DOCX were not substituted for it.
- Bounded academic drafts came from `manuscript_writer`; the coordinator
  checked the source claims and applied the changes. EVSU chapter order,
  existing typography, citations, unrelated material and native tables remain.

## Changed manuscript areas

| Area | Revision |
| --- | --- |
| Chapter I scope/limitations and terms | Unknown evaluation limit; external approval; provisional +90 formula; protected-save definition; planned pilot wording |
| Chapter II operational descriptions | Enabled torture RORP terminology and protected-save/audit behavior; literature citations retained |
| Chapter III modeling and workflow | Investigator/Supervisor/Admin boundaries, external decision recording, protected timeline transaction, training descriptions |
| Tables 3.5, 3.9, 3-10 | Nullable classification and case version; base/granted/actual/request date distinctions; action-specific audit notes |
| Table 3-11 and Figure 3.8 | Correct `roles.role_name` and remove the nonexistent unique constraint |
| Figures 3.4–3.8 | System context, case-monitoring data flow, use cases, protected timeline activity and current domain ERD |
| Chapter IV and Table 4.2 | Enabled 60/90 boundary behavior; dated historical CI result alongside fresh local technical result |
| Chapter V evaluation/recommendations | Synthetic tests distinguished from pending CHR evaluation; validation required before adoption |

The diagram replacements remove obsolete Supervisor closure approval. The
dedicated assigned-Investigator closure endpoint follows current code and the
vault; the conflicting ordinary-form instruction in AGENTS.md remains flagged.
The ERD adds no extension approval role or table. [Editable SVGs, PNG assets and
the rebuild source](figures/README.md) accompany all five figures.

## Verification

- Fresh 12 November run: **462 tests passed, 2,278 assertions, zero skips** on
  in-memory SQLite with synthetic fixtures. The run took 81.30 seconds; this
  is test duration, not office response time or a performance claim.
- Native document readback verifies actual table rows, classification and
  extension fields, updated claims and the five embedded figure objects.
- PDF export/render review checks page fit, captions, new field rows and figure
  content. Schema headers repeat and rows do not split; obsolete ERD spacers
  were removed. No whole-document restyling was performed.
- Figure generation parses each SVG and checks that all five sources exist.
  Local Markdown links and diff whitespace are checked before handoff.
- Independent review found an incorrect role-column label in the newly authored
  ERD. It was corrected against the migration, regenerated and re-embedded;
  Table 3-11 was aligned. The final exported ERD was inspected again.
  A focused independent recheck confirmed the ERD and planned-pilot wording
  corrections, with no remaining findings in its scope. The reviewer inspected
  supplied native readback and rendered pages; direct live retrieval and a
  whole-manuscript audit were not part of that review.

The initial native revision was
`AHj4eMT-0ADWoIzGVOYuqtMhgBuWi_K5GH0G2wUIEJGbYPWpmoZeH1wxcrOeemW8z1qyoOB5W-SFVkue7RFyLGc-Mn_41MBJCIU9DrEwLXE`.
The reviewed correction revision is
`AHj4eMRarEvE4sWmVG0dHM-JFzGOnoQ1BIuIZHnLH1HGugyFllrH3H4DbulX3fMJ11qDgVVLS5D7ma6c82YtB8c05u4B31e206Tsx5Pcmjs`.
Historical CI and deadline-service links retain their original destinations and
are explicitly labeled historical; no current published commit was invented for
the uncommitted implementation.

## Limits

The 90-day formula remains a documented prototype assumption, not a new CHR
answer or institutional approval. Stage 4 deployment instructions and Stage 5
MariaDB concurrency, guided Narrator and CHR response collection remain separate
approval checkpoints. Unknown classification decisions remain with staff.

This bounded revision is not a full manuscript audit. Unrelated existing
literature claims, citations, software-inventory descriptions and schema-table
details still require the team's wider manuscript review. No CHR evaluation,
screen-reader acceptance, office deployment, commit or push is asserted.
