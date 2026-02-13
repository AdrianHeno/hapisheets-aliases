# UI components (Tailwind)

Minimal design system under `templates/components/`: typography, buttons, forms, table, badges, flash messages. Style: clean SaaS admin, neutral palette, max-width 1000px, lots of whitespace (see `docs/ui-spec.md`). Page layout uses a centered container on `<main>` in `base.html.twig`.

Tailwind is loaded via CDN in `base.html.twig` so these partials take effect when used.

## Typography

- **components/heading.html.twig** — `level` (1–6), `content` (string) or block. Optional `class`.  
  Example: `{% include 'components/heading.html.twig' with { level: 1, content: 'Dashboard' } %}`.

## Buttons

- **components/button.html.twig** — `variant`: `primary` | `secondary`, `type`: `submit` | `button`, `label` (text). For a link styled as a button, pass `url`. Optional `class`.  
  Example: `{% include 'components/button.html.twig' with { variant: 'primary', label: 'Save', type: 'submit' } %}`.

## Forms

- **components/form_group.html.twig** — Label above, optional errors. Pass `label_for`, `label_text`, `required`, `errors` (array). Put the input in block `input` (use `{% embed %}...{% block input %}...{% endblock %}...{% endembed %}`).
- **components/form_errors.html.twig** — Inline errors under a field. Pass `errors` (array of strings).
- **components/input_classes.html.twig** — Macro for input class string.  
  `{% import 'components/input_classes.html.twig' as input_ui %}` then e.g. `class="{{ input_ui.classes() }}"` on an `<input>`.

## Table

- **components/table.html.twig** — Zebra rows, subtle borders. Use `{% embed %}...{% block thead %}...{% endblock %}{% block tbody %}...{% endblock %}...{% endembed %}`. Put `<th>`/`<td>` with classes e.g. `px-4 py-3 text-left text-sm font-medium text-neutral-700` (th) or `text-neutral-900` (td) inside thead/tbody.

## Badges

- **components/badge.html.twig** — `variant`: `enabled` | `disabled` | `success` | `error` | `info`, `label` (text). Optional `class`.  
  Example: `{% include 'components/badge.html.twig' with { variant: 'enabled', label: 'Enabled' } %}`.

## Flash messages

- **components/flash_messages.html.twig** — Renders `app.flashes` as success/info/error banners. No arguments.  
  Example: `{% include 'components/flash_messages.html.twig' %}`.
