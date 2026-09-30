# 02 — PDF Analysis

**Source file:** `C:\Users\butom\Downloads\PHP 8 Objects, Patterns, and Practice (Matt Zandstra).pdf`
**Extraction tool:** Poppler `pdftotext -layout -enc UTF-8` (available at `C:\poppler\Library\bin`)
**Extracted working copy:** `C:\Users\butom\AppData\Local\Temp\opencode\phpbook\book.txt` (session temp — the importer in Phase 1 reproduces this inside the app)

## Bibliographic data

| Field | Value |
|---|---|
| Title | PHP 8 Objects, Patterns, and Practice |
| Subtitle | Mastering OO Enhancements, Design Patterns, and Essential Development Tools |
| Edition | 6th |
| Author | Matt Zandstra (Brighton, UK) |
| Publisher | Apress (Springer), 2021 |
| ISBN-13 pbk / ebook | 978-1-4842-6790-5 / 978-1-4842-6791-2 |
| DOI | 10.1007/978-1-4842-6791-2 |
| PDF pages | 842 |
| Printed pages | 833 (front matter xxv + body 817 + index) |
| Publisher figures | 102 |
| Companion code | `github.com/Apress/php-8-objects-patterns-practice` (src/ + test/, keyed to listing numbers) |

## Content inventory (measured, not estimated)

| Asset | Count |
|---|---|
| Words extracted | ~178,500 |
| Code listings (`// listing NN.NN`) | **709** |
| Unique figures (`Figure N-M`) | **100** |
| Tables | ~44 |
| TOC entries | **315** (≈98 sections, ≈191 subsections) |
| Exercises / quizzes / flashcards / projects in book | **≈0** |
| `<?php` open tags | 23 (most listings are class/method fragments) |

## Structure: parts and chapters

Page ranges are **printed** pages. Listings/figures counted from the extracted text.

### Part I — Objects

| Ch | Title | Pages | PDF start | Listings | Figures |
|---|---|---|---|---|---|
| 1 | PHP: Design and Management | 3–11 | 24 | 0 | 0 |
| 2 | PHP and Objects | 13–20 | 33 | 0 | 0 |
| 3 | Object Basics | 21–77 | 41 | 76 | 0 |
| 4 | Advanced Features | 79–152 | 98 | 126 | 0 |
| 5 | Object Tools | 153–208 | 172 | 93 | 1 |
| 6 | Objects and Design | 209–238 | 228 | 12 | 20 |

### Part II — Patterns

| Ch | Title | Pages | PDF start | Listings | Figures |
|---|---|---|---|---|---|
| 7 | What Are Design Patterns? Why Use Them? | 241–251 | 259 | 0 | 0 |
| 8 | Some Pattern Principles | 253–272 | 270 | 19 | 6 |
| 9 | Generating Objects | 273–330 | 290 | 72 | 10 |
| 10 | Patterns for Flexible Object Programming | 331–362 | 348 | 41 | 5 |
| 11 | Performing and Representing Tasks | 363–419 | 380 | 63 | 9 |
| 12 | Enterprise Patterns | 421–490 | 437 | 47 | 11 |
| 13 | Database Patterns | 491–556 | 507 | 53 | 10 |

### Part III — Practice

| Ch | Title | Pages | PDF start | Listings | Figures |
|---|---|---|---|---|---|
| 14 | Good (and Bad) Practice | 559–569 | 574 | 0 | 0 |
| 15 | PHP Standards (PSR-1/12/4) | 571–594 | 585 | 22 | 0 |
| 16 | Using and Creating Components with Composer | 595–611 | 608 | 0 | 3 |
| 17 | Version Control with Git | 613–644 | 625 | 0 | 7 |
| 18 | Testing with PHPUnit | 645–686 | 657 | 30 | 2 |
| 19 | Automated Build with Phing | 687–717 | 699 | 23 | 0 |
| 20 | Vagrant | 719–732 | 730 | 0 | 2 |
| 21 | Continuous Integration | 733–763 | 744 | 7 | 14 |
| 22 | Objects, Patterns, Practice (synthesis) | 765–778 | 775 | 1 | 0 |

### Back matter

| Unit | Pages | Notes |
|---|---|---|
| Appendix A — Bibliography | 781–783 | books, articles, sites |
| Appendix B — A Simple Parser | 785–816 | recursive-descent scanner + parser, ~25 listings (numbered beyond ch.22) |
| Index | 817–833 | |

## Section-level detail (what a lesson is carved from)

TOC depth is 3 levels: **chapter → section → subsection**. Examples of lesson-sized units:

- Ch 3: Classes and Objects / Setting Properties / Working with Methods / Constructor (+ Constructor Property Promotion) / Default & Named Arguments / Arguments and Types (primitive, object, mixed, union, nullable) / Return Types / Inheritance (problem, working, visibility, typed properties)
- Ch 4: Static / Constants / Abstract / Interfaces / Traits (11 subsections) / Late Static Binding / Handling Errors (+ Exceptions) / Final / Interceptors (`__get`,`__set`,…) / Destructor / `__clone` / `__toString` / Closures & anonymous functions / Anonymous classes
- Ch 5: Namespaces / Autoload / Class-object functions / Reflection API / Attributes
- Ch 9–13: every pattern is consistently structured **The Problem → Implementation → Consequences** (some also "Issues")

## Page mapping (printed → PDF)

Offsets are **not constant**: chapters open on odd pages and blank verso pages are absent from the PDF. Verified anchors used by the importer:

```
3→24   13→33   21→41   79→98   153→172  209→228  241→259  253→270
273→290 331→348 363→380 421→437 491→507  559→574  571→585  595→608
613→625 645→657 687→699 719→730 733→744  765→775  100→119  300→317
500→516 700→712 800→809
```

**Rule:** store both `page_printed_*` and `page_pdf_*`; derive the PDF page by locating the printed number in `pdf_pages` (and interpolate only when flagged).

## Running examples (reused across chapters)

| Example | Where | Purpose |
|---|---|---|
| `ShopProduct` / `BookProduct` / `CdProduct` / `TrackProduct` | Ch 3, 4, 6, 10 | first class, inheritance, decorators |
| `Chargeable` interface | Ch 4, 8, 9 | interfaces, polymorphism, factories |
| Interpreter mini-language + EBNF | Ch 11 | Interpreter pattern; extends to Appendix B parser |
| `Registry`, `Request`, `ApplicationHelper`, Front Controller mini-framework | Ch 12 | enterprise patterns, layering |
| `DataMapper`, `VenueMapper`, `IdentityObject`, `ObjectWatcher`, `Unit of Work` | Ch 13 | persistence patterns |
| Full scanner/parser | Appendix B | capstone elective |

## Findings that shape the product

1. **Not a beginner book.** It assumes fluent PHP. Zero coverage of variables, types, control flow, loops, arrays, strings, forms/GET-POST, sessions, files, basic SQL. → **Stage 0 (PHP Foundations)** is AI-generated, `source = ai`, never attributed to the book.
2. **No built-in pedagogy.** No exercises, quizzes, flashcards, projects or interview questions. The whole practice layer is generated beside the book and admin-gated.
3. **Dated tooling.** Targets PHP 8.0 (2021): Phing (182 mentions), Vagrant (169), Jenkins (61), Selenium IDE, PHPUnit 9, PHPCS. Missing PHP 8.1–8.4 (enums, `readonly`, fibers, first-class callables, property hooks, asymmetric visibility), Docker, GitHub Actions, PHPStan/Psalm/Rector, Pest, Composer 2. → every affected lesson gets a `BOOK TEACHES → MODERN PHP → WHY` panel and `is_outdated = 1`.
4. **Listings are fragments.** Book text has `// listing NN.NN` markers; full runnable files + rudimentary PHPUnit tests live in the Apress repo → imported as `code_examples.external_ref`.
5. **Figures are images.** 100 figure references must be preserved (`figure_ref`, `page_pdf`) while Mermaid replacements are generated as AI diagrams.
6. **Extraction hygiene.** Text contains `\x07`/`\x08` control characters (1,000 total) and U+FFFD runs from dot-leader glyphs → sanitizer strips them before storage.
7. **Copyright.** Verbatim book text stays local for personal study; UI quotes are short excerpts with citations, not full chapter dumps.

## Extraction pipeline (implemented in Phase 1)

```
pdfinfo  → page count, metadata
pdftotext -layout -enc UTF-8 → pages split on \f
sanitize (\x07,\x08,U+FFFD,nbsp) → pdf_pages rows
printed-page detection (trailing standalone number) → page_printed
TOC parse (315 entries, indent → level, dot-leader page ref) → chapters/sections
body verify (heading found on expected PDF page?) → section.page_pdf correction
low extraction quality (too few words, no printed number) → page_review_flags
```
