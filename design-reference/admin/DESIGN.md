# DESIGN.md

## Purpose

This document defines the visual identity, interaction principles, and frontend architecture guidelines for this project.

Its purpose is to ensure that every screen, component, layout, and interaction follows a single, cohesive design language.

Every frontend implementation MUST follow this document.

When this document conflicts with individual preferences or assumptions, this document takes precedence.

---

# Primary Goal

Build interfaces that are:

- Modern
- Professional
- Clean
- Predictable
- Highly reusable
- Consistent
- Accessible
- Responsive
- Easy to maintain

The interface should feel like it was designed by a single designer and implemented by a single engineer.

---

# Design Philosophy

This design system prioritizes clarity over decoration.

The content is always more important than visual effects.

Every element must have a purpose.

Avoid visual noise.

Avoid unnecessary complexity.

Prefer whitespace over separators.

Prefer consistency over creativity.

Users should immediately recognize familiar patterns throughout the application.

---

# Visual Language

The interface should communicate:

- professionalism
- trust
- simplicity
- speed
- organization

Characteristics:

- Light content area
- Dark sidebar
- White cards
- Soft shadows
- Rounded corners
- Low visual noise
- Generous spacing
- Minimal borders
- Linear icons
- Smooth transitions

Avoid flashy effects.

Avoid heavy gradients.

Avoid excessive shadows.

Avoid colorful interfaces.

---

# Visual Hierarchy

Every page should follow this hierarchy whenever applicable.

1. Page Header
2. Breadcrumb
3. Statistics Cards
4. Search
5. Filters
6. Main Content
7. Pagination

Never invert this hierarchy without a valid reason.

---

# Layout Principles

The application consists of:

- Sidebar
- Top Navigation
- Content Area
- Footer (optional)

Content should never touch the viewport edges.

Use consistent spacing throughout the application.

Content should remain visually balanced regardless of screen size.

---

# Sidebar

The sidebar is the primary navigation component.

Rules:

- Fixed on desktop
- Drawer on mobile
- Supports collapse and expand
- Supports nested navigation
- Supports icon-only collapsed mode
- Supports user profile dropdown
- Remembers collapsed state
- Only one active navigation item
- Only one expanded navigation branch by default

Nested items must be visually distinguishable.

The active route should always be obvious.

---

# Navigation

Navigation should be predictable.

Rules:

- One primary navigation
- Clear grouping
- Consistent icons
- Consistent spacing
- Visible active state
- Smooth expand/collapse animation

Avoid deeply nested navigation.

Maximum recommended depth:

3 levels.

---

# Cards

Cards are the primary content container.

Every card should:

- Have white background
- Have subtle border
- Have soft shadow
- Have generous padding
- Maintain consistent corner radius

Do not place large amounts of content directly on the page background.

Use cards instead.

Never create colorful cards unless they represent status information.

---

# Dashboard

Dashboards should follow this order:

1. Statistics
2. Charts
3. Recent Activity
4. Tables
5. Secondary Information

Statistics should always appear above detailed information.

---

# Forms

Forms should be simple and predictable.

Rules:

- Labels above fields
- Full-width inputs
- Related fields grouped together
- Maximum two columns whenever possible
- Long forms divided into sections
- Primary action aligned consistently
- Secondary actions grouped together

Never use placeholders as labels.

Never rely on color alone to communicate validation.

---

# Inputs

All form controls should appear visually consistent.

Every input should provide:

- Label
- Helper text (when necessary)
- Error message
- Disabled state
- Readonly state
- Loading state (when applicable)

Validation should be obvious but not intrusive.

---

# Buttons

Buttons communicate actions.

There should only be one primary action within the same visual context.

Priority:

Primary

↓

Secondary

↓

Outline

↓

Ghost

↓

Link

Danger actions must be visually distinct.

Icon-only buttons should only be used when their meaning is universally recognizable.

---

# Tables

Tables should include:

- Search
- Filters
- Sorting
- Pagination
- Empty state
- Loading state
- Row actions

Rows should remain readable.

Avoid excessive columns.

Actions should remain grouped.

---

# Lists

Lists should prioritize readability.

Use lists when:

- visual comparison is unnecessary
- hierarchy matters more than density

---

# Grid Views

Use grid layouts for:

- products
- users
- media
- cards
- galleries

Grid cards should maintain consistent height whenever possible.

---

# Modals

Use modals sparingly.

Modal sizes:

- Small
- Medium
- Large
- Fullscreen

Use confirmation dialogs for destructive actions.

Long forms should not be placed inside small modals.

ESC should close the modal unless explicitly disabled.

Backdrop click should close the modal unless explicitly disabled.

---

# Confirmation Dialogs

Confirmation dialogs should contain:

- Title
- Description
- Cancel button
- Confirm button

Danger confirmations should clearly explain the consequences.

Never use vague confirmation messages.

---

# Prompt Dialogs

Prompt dialogs should request only the minimum required information.

Avoid long forms inside prompt dialogs.

---

# Empty States

Every empty state should include:

- Illustration or icon
- Title
- Short explanation
- Primary action

Empty states should encourage the next action.

---

# Loading States

Avoid blank pages.

Use:

- Skeletons
- Loading indicators
- Progressive rendering

Loading should preserve layout stability.

---

# Notifications

Notifications should be concise.

Each notification should contain:

- Status
- Message
- Optional action

Notifications should disappear automatically unless user interaction is required.

---

# Icons

Use a single icon library throughout the project.

Icons should:

- remain consistent
- use consistent sizes
- align properly
- never replace labels when labels improve clarity

---

# Typography

Maintain a clear hierarchy.

Use typography to communicate importance instead of color.

Prefer:

- whitespace
- size
- weight

Avoid excessive font sizes.

---

# Spacing

Spacing should remain consistent across the application.

Never invent spacing values.

Use predefined spacing only.

Whitespace improves readability.

Do not compress layouts unnecessarily.

---

# Colors

Colors are semantic.

Never choose colors arbitrarily.

Every color should represent a meaning.

Primary color identifies the primary action.

Success indicates successful operations.

Warning indicates caution.

Danger indicates destructive actions.

Information indicates neutral contextual feedback.

---

# Shadows

Use subtle elevation.

Prefer soft shadows.

Avoid dramatic shadows.

Elevation should communicate hierarchy rather than decoration.

---

# Border Radius

Use consistent corner radius across the application.

Avoid mixing multiple corner styles.

Rounded interfaces create a more approachable experience.

---

# Animations

Animations should communicate state changes.

Animation should never become decoration.

Use animations for:

- sidebar
- dropdown
- modal
- accordion
- page transitions

Animations should feel responsive.

---

# Accessibility

Every interface must be accessible.

Always provide:

- labels
- keyboard navigation
- sufficient contrast
- ARIA attributes
- visible interaction feedback

Do not rely exclusively on color.

---

# Responsiveness

Design mobile-first.

Layouts should adapt instead of hiding functionality.

Avoid horizontal scrolling.

Navigation should become a drawer on mobile devices.

Content should reorganize naturally.

---

# Interaction Rules

Always provide visual feedback for interactive elements.

Never use Tailwind ring utilities except:

- focus:ring-0
- focus-visible:ring-0

Preferred focus styles:

- outline-none
- focus:ring-0
- focus-visible:ring-0

Do not introduce custom focus effects without necessity.

---

# Reuse Before Creating

Before creating any component, ask:

- Does this component already exist?
- Can an existing component solve this problem?
- Can an existing variant be reused?

Always prefer reuse.

Avoid component duplication.

---

# Decision Process

Before implementing a new screen:

1. Understand the user's goal.
2. Identify the page type.
3. Reuse existing layouts.
4. Reuse existing components.
5. Follow existing spacing.
6. Follow existing typography.
7. Follow existing interaction patterns.
8. Verify responsiveness.
9. Verify accessibility.
10. Verify consistency.

---

# Never Do

Never invent new colors.

Never invent new spacing.

Never invent new typography.

Never invent new component styles.

Never introduce a second design language.

Never mix icon libraries.

Never use inline CSS.

Never use arbitrary Tailwind values unless explicitly approved.

Never use `ring` utilities except `ring-0`.

Never use `!important`.

Never create visually inconsistent pages.

Never prioritize aesthetics over usability.

Never sacrifice consistency for originality.

---

# Quality Checklist

Before completing any frontend implementation, verify:

- Responsive
- Accessible
- Consistent
- Reusable
- Uses existing layouts
- Uses existing components
- Uses semantic colors
- Uses predefined spacing
- Uses predefined typography
- Uses predefined shadows
- Uses predefined radius
- Uses smooth transitions
- Has no duplicated UI
- Has no inline CSS
- Has no arbitrary Tailwind values
- Has no unnecessary complexity
- Uses `focus:ring-0`
- Uses `focus-visible:ring-0`
- Matches the overall design language

If any item fails this checklist, the implementation is not complete.
