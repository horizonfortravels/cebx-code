---
name: frontend-design
description: Distinctive, production-grade frontend design for HTML, CSS, Blade, and browser UI work. Use when restyling or designing pages, shells, dashboards, auth flows, landing sections, or other user-facing interfaces that need a clear visual point of view, intentional typography, color, motion, spatial composition, and real polish instead of generic AI-looking output.
---

# Goal
Create distinctive, production-grade frontend direction that feels intentional, polished, and usable in real product work.

# When to Use
- Design or restyle Blade, HTML, or CSS-driven user interfaces.
- Improve visual hierarchy, spacing, typography, color, or motion on a browser-facing surface.
- Turn a vague "make this look professional" request into a clear design direction.
- Support repo-specific UI skills that need stronger overall visual taste.

# When Not to Use
- Pure backend, routing, or data work with no design surface.
- Tiny bug fixes that do not require design judgment.
- Tasks that are fully constrained by an existing strict design system and only need faithful implementation.

# Inputs
- The target surface, audience, and business goal.
- Existing layout, component, and CSS constraints.
- Any brand cues, product tone, or competitive references supplied with the task.

# Outputs
- A clear visual direction for the surface.
- Stronger hierarchy, typography, color, spacing, and interaction guidance.
- UI changes or recommendations that feel deliberate rather than generic.

# Instructions
1. Choose a clear aesthetic direction before changing details. Make the interface feel designed on purpose, not merely cleaned up.
2. Use typography as a primary design tool. Establish contrast in size, weight, rhythm, and density instead of relying on extra chrome.
3. Build a restrained color system with a point of view. Use accent color, surface contrast, and emphasis intentionally rather than spreading equal visual weight everywhere.
4. Use motion sparingly but meaningfully. Favor page-load choreography, state transitions, and emphasis that support comprehension.
5. Shape space deliberately. Use composition, negative space, alignment, and content blocks to make the interface feel premium and easy to scan.
6. Start from mobile and narrow widths, then scale up to desktop without letting the layout become generic or empty.
7. Match the implementation stack already in the repo. Work cleanly with Blade, HTML, CSS, and shared component systems when they are present.
8. Treat "professional" as clarity plus polish, not as bland safety. Preserve usability while still making strong visual choices.

# Guardrails
- Do not default to generic purple gradients, stock SaaS layouts, or interchangeable hero-card patterns.
- Do not overload the screen with decorative noise that weakens clarity.
- Do not ignore accessibility, contrast, or readability in the name of style.
- Do not force a React-only or Tailwind-only approach when the surface is plain HTML, CSS, or Blade.

# Verification
- Check whether the interface has a clear visual hierarchy at a glance.
- Check mobile and desktop compositions for balance and intent.
- Check whether typography, color, and spacing feel coherent across the whole surface.
- Check whether the result feels specific to the product instead of like a generic AI template.
