# BarePitch — UI Interaction Specifications
Version 1.0 — April 2026

---

# 1. Purpose

This document defines the UI interaction behavior for BarePitch.

This document defines frontend interaction rules only.

Permissions, validation, and match-state authority remain backend concerns defined elsewhere.

The goal is to ensure:
- consistency
- predictability
- low cognitive load
- fast interaction
- mobile-first usability
- minimal visual noise

This document focuses on:
- interaction behavior
- navigation
- touch handling
- layout rules
- visual hierarchy
- modal behavior
- icon behavior
- polling behavior
- state feedback
- responsive behavior

BarePitch principle:

> BarePitch shows what matters, when it matters. Nothing more.

---

# 2. Core UI Philosophy

---

## 2.1 Calm Interface

The interface must feel:
- calm
- focused
- stable
- predictable

The UI must avoid:
- visual overload
- animation-heavy behavior
- dashboard clutter
- excessive simultaneous information

---

## 2.2 Action-Oriented Design

Every screen exists primarily to support:
- one context
- one task
- one decision flow

The UI must prioritize:
1. current action
2. current state
3. current consequence

---

## 2.3 Mobile-First Interaction

BarePitch is designed primarily for smartphone use during football matches.

The UI must therefore optimize for:
- outdoor readability
- one-handed use
- thumb interaction
- unstable attention
- fast correction behavior

Desktop support is secondary.

---

# 3. Global Layout Rules

---

## 3.1 Vertical Flow

Primary layout direction:
- vertical

Reason:
- natural mobile scrolling
- lower thumb travel complexity
- reduced horizontal interaction

Horizontal scrolling should be avoided.

---

## 3.2 Screen Sections

A screen should visually separate:
1. context
2. primary actions
3. secondary information
4. history or timeline

---

## 3.3 Sticky Context

Critical context remains visible:
- active match
- score
- phase
- timer

Recommended:
- sticky top bar during live match

---

## 3.4 Maximum Depth

Recommended navigation depth:
- maximum 3 levels

Avoid:
- deeply nested menus
- hidden interaction paths

---

# 4. Visual Hierarchy

---

## 4.1 Priority Order

The interface prioritizes:
1. active state
2. current score
3. current action
4. lineup
5. timeline
6. statistics

Statistics are always lower priority than live actions.

---

## 4.2 Contrast Rules

The UI must support:
- daylight readability
- outdoor usage
- quick scanning

Requirements:
- strong text contrast
- large interactive areas
- readable spacing

Avoid:
- low-contrast gray-on-gray designs
- thin typography
- decorative visuals

---

## 4.3 Typography

Typography should:
- remain simple
- prioritize readability
- avoid decorative fonts

Recommended:
- system fonts
- large readable labels
- medium font weight

---

# 5. Navigation Behavior

---

## 5.1 Main Navigation

Primary navigation uses:
- icons
- short labels
- a bottom navigation bar on smartphone layouts

On smartphone layouts, the bottom navigation bar should provide stable access to top-level sections.

It should not behave like a fast-changing action strip.

Bottom navigation items should keep visible labels.

Icons support recognition, but labels preserve orientation and reduce ambiguity.

The bottom navigation bar should be positioned for one-handed thumb use.

Recommended count:
- 3 to 5 top-level destinations

Recommended navigation items:
- Dashboard
- Teams
- Matches
- Trainings
- Statistics
- Settings

If the product has more than 5 top-level areas, use prioritization or a secondary destination pattern instead of crowding the bottom bar.

---

## 5.2 Active Navigation State

The active section must:
- remain visually distinct
- use high contrast
- remain obvious during scrolling

Top-level destinations in the bottom bar should remain stable enough that users do not need to re-learn navigation from screen to screen.

---

## 5.3 Navigation Persistence

During live matches:
- top-level navigation may remain visually quieter
- match controls should dominate screen space
- contextual live-match controls should appear above the bottom navigation bar or within the content area

Use a hybrid model:
- bottom navigation bar for stable top-level navigation
- contextual toolbar, segmented control, or local action bar for live-match sections like lineup, timeline, and controls

Live-match subareas should not replace the app's top-level navigation model unless the product intentionally enters a full-screen dedicated live mode.

---

# 6. Touch Interaction Rules

---

## 6.1 Minimum Touch Size

All interactive elements must support:
- minimum 44 × 44 pixels

Preferred:
- 48 × 48 pixels

---

## 6.2 Spacing

Touch targets must have enough separation to prevent accidental activation.

Recommended:
- minimum 8px spacing between critical actions

---

## 6.3 Thumb Zones

Frequently used actions should remain:
- in lower screen regions
- reachable by thumb

Critical live controls should avoid top-corner placement.

---

# 7. Swipe Behavior

---

## 7.1 Purpose

Swipe interactions reduce accidental activation for critical match actions.

---

## 7.2 Swipe Actions

Recommended swipe-only actions:
- start match
- end period
- start extra time
- finish match
- finish penalty shootout

---

## 7.3 Swipe Threshold

Recommended threshold:
- minimum 60% swipe distance

Accidental short movement must not trigger action.

---

## 7.4 Confirmation

After successful swipe:
- confirmation modal appears

Swipe alone is not enough for critical actions.

---

# 8. Modal Behavior

---

## 8.1 Purpose

Modals isolate focused interaction.

They should:
- reduce distraction
- avoid screen transitions
- support quick decisions

---

## 8.2 Modal Rules

A modal:
- must focus on one action only
- must not contain unrelated navigation
- must be dismissible unless action is critical

---

## 8.3 Modal Stacking

Modal stacking is discouraged.

Recommended:
- maximum one active modal

---

## 8.4 Critical Confirmation Modals

Required for:
- match start
- period end
- match finish
- shootout finish
- destructive deletion

---

# 9. Button Behavior

---

## 9.1 Primary Buttons

Primary actions:
- visually dominant
- highest contrast
- largest emphasis

Example:
- Start Match

---

## 9.2 Secondary Buttons

Secondary actions:
- visually softer
- lower contrast
- less prominent

Example:
- Cancel

---

## 9.3 Disabled Buttons

Disabled buttons:
- visibly inactive
- clearly unavailable
- still readable

Avoid:
- hidden unavailable actions without explanation

---

## 9.4 Icon-First Action Design

Use intuitive icons as the default for repeated high-frequency actions when this saves space and reduces decision time.

Preferred icon-first actions:
- live match event buttons
- timeline event markers
- edit actions
- delete actions
- guest-player markers
- card indicators
- substitution triggers

Use text-first or icon-plus-text when:
- the action is rare
- the action is destructive
- the icon meaning could be ambiguous
- the user is making a high-risk state transition

Critical actions such as match start, period end, extra-time start, and finish match should retain explicit text or confirmation text even if initiated from an icon-led control.

Primary bottom-navigation items should use icon plus label rather than icon-only presentation.

Never icon-only:
- match start
- period end
- start extra time
- start penalty shootout
- finish penalty shootout
- finish match
- destructive delete confirmation
- logout

---

# 10. Icon Specifications

---

## 10.1 Purpose

Icons reduce text density and improve recognition speed.

---

## 10.2 Icon Rules

Icons must:
- remain recognizable at small size
- remain understandable without color alone
- stay visually simple
- communicate action or status faster than equivalent short text where possible

Avoid:
- overly detailed icons
- decorative icon packs
- inconsistent icon styles
- icons that require explanation every time they appear

---

## 10.3 Recommended Match Icons

Goal:
- football

Substitution:
- arrows

Yellow card:
- yellow rectangle

Red card:
- red rectangle

Injury:
- medical cross or bandage

Note:
- note or comment icon

Delete:
- trash bin

Edit:
- pencil

Guest player:
- superscript G indicator

---

## 10.4 Icon Labels

Frequently used icons may omit labels after learning.

Less obvious icons should retain text labels.

Preferred approach:
- icon only on dense mobile toolbars for high-frequency actions
- icon plus text on first-exposure screens, settings, and destructive flows
- icon plus accessible label in markup for all interactive controls

Tooltip or press-and-hold label reveal is recommended for icon-only controls where space is tight.

---

# 11. Lineup Interaction Specifications

---

## 11.1 Grid Behavior

The lineup uses:
- 10 rows
- 11 columns

Players appear as draggable or selectable field elements.

---

## 11.2 Position Selection

Position movement should support:
- drag and drop
or
- tap-select and tap-place

Both are acceptable if interaction remains reliable on mobile.

---

## 11.3 Occupied Grid Slots

Recommended behavior:
- prevent duplicate occupancy
- visually indicate blocked positions

---

## 11.4 Bench Behavior

Bench players:
- appear below field
- remain visually separated
- remain easily selectable

---

# 12. Timeline Interaction Specifications

---

## 12.1 Purpose

The timeline provides:
- chronological visibility
- correction access
- event confirmation

---

## 12.2 Order

Recommended default:
- newest first during live play

Reason:
- reduced scrolling
- current context visibility

---

## 12.3 Timeline Entries

Timeline entries must remain:
- compact
- scannable
- icon-supported

Dense event rows should prefer recognizable icons over repeated event text where possible.

Each entry should show:
- event type
- player
- minute

---

## 12.4 Timeline Editing

Editable events:
- visibly marked
- open correction modal on tap

---

# 13. Goal Registration UI

---

## 13.1 Flow Design

Goal registration must require:
- minimal navigation
- minimal text input

Preferred interaction:
- tap-driven flow

---

## 13.2 Team Selection

The coach's own team should be preselected by default.

---

## 13.3 Player Selection

Player lists should:
- prioritize active field players
- remain visually large
- support fast selection

---

## 13.4 Goal Zone Matrix

The goal zone selector:
- uses 3 × 3 grid
- supports single tap selection
- visually highlights selected zone

Zone selection must remain optional unless explicitly configured otherwise.

---

# 14. Match State Feedback

---

## 14.1 State Visibility

Current state must remain obvious:
- planned
- prepared
- active
- halftime
- extra time
- shootout
- finished

The UI may display these as finer-grained visible phases even though the core persisted match status remains:
- planned
- prepared
- active
- finished

---

## 14.2 Visual Indicators

States should use:
- labels
- icons
- controlled color usage

Avoid:
- flashing indicators
- aggressive animations

---

# 15. Loading Behavior

---

## 15.1 Loading Feedback

Every delayed interaction must provide:
- loading indicator
or
- disabled state feedback

---

## 15.2 Blocking Behavior

Critical actions should:
- temporarily disable repeated input
- prevent duplicate submissions

---

## 15.3 Skeleton Loading

Optional:
- lightweight skeleton placeholders

Avoid:
- heavy animated skeleton systems

---

# 16. Polling and Refresh Behavior

---

## 16.1 Livestream Polling

Recommended polling interval:
- 60 seconds

Reason:
- lower server load
- acceptable freshness for public viewers

---

## 16.2 Coach Interface Refresh

Coach interface should update:
- immediately after successful server response

Avoid:
- delayed visual confirmation

---

## 16.3 Failed Refresh Behavior

If polling fails:
- keep current visible state
- show subtle connection warning if repeated failures occur

Avoid:
- full-screen interruption

---

# 17. Error State Behavior

---

## 17.0 Text Input Behavior

Free-text fields should help users stay inside documented limits.

UI requirements:
- use `maxlength` attributes that match the route and domain-model limits
- show remaining-character feedback only for note fields or longer fields where it helps
- avoid noisy counters for short name fields unless validation errors are common
- keep validation messages close to the field
- preserve safe submitted text after validation errors
- prevent long unbroken text from breaking layouts

Default UI limits:
- person first name: 80 characters
- person last name: 80 characters
- names and labels: 120 characters
- opponent name: 120 characters
- email address: 254 characters
- short notes and live match notes: 500 characters
- long internal/admin notes: 2000 characters

These UI limits are usability aids only. Server-side validation remains authoritative.

## 17.1 Validation Errors

Validation errors should:
- appear close to action
- remain readable
- explain the issue directly

Example:
- Lineup is incomplete
- Player cannot re-enter after red card

---

## 17.2 System Errors

System errors should:
- remain calm
- avoid technical language
- preserve entered data where possible

---

## 17.3 Offline or Weak Network Behavior

If network instability occurs:
- preserve current screen state
- avoid full reset
- allow retry

---

# 18. Responsive Behavior

---

## 18.1 Smartphone Priority

The smallest supported experience is:
- smartphone portrait mode

This is the primary design target.

---

## 18.2 Tablet Behavior

Tablet layouts may:
- show more simultaneous information
- widen lineup view
- widen timeline

But behavior must remain consistent.

---

## 18.3 Desktop Behavior

Desktop:
- supports wider layouts
- may show split panels
- should not introduce desktop-only workflows

---

# 19. Animation Rules

---

## 19.1 Purpose

Animations should:
- support clarity
- support orientation
- support feedback

Animations must never exist purely for decoration.

---

## 19.2 Recommended Animations

Acceptable:
- subtle transitions
- modal fade
- swipe confirmation movement
- timeline insertion animation

Avoid:
- bouncing elements
- excessive motion
- animated dashboards

---

## 19.3 Duration

Recommended:
- 150–250ms transitions

Long animations are discouraged.

---

# 20. Accessibility Specifications

---

## 20.1 Color Independence

Meaning must never rely only on color.

Cards and statuses should include:
- icon
- label
or
- pattern

---

## 20.2 Contrast

Recommended:
- WCAG AA contrast minimum

---

## 20.3 Readability

Text should remain readable:
- outdoors
- in motion
- under stress

---

## 20.4 Interaction Clarity

Users should never wonder:
- if an action succeeded
- if a button is disabled
- if a screen is loading

Users should also never have to decode unclear iconography during live use.

If an icon saves space but increases hesitation, the icon choice is wrong and text should be restored.

---

# 21. Explicit UI Non-Goals

BarePitch intentionally avoids:
- dashboard overload
- decorative animations
- social features
- gamification
- chat systems
- infinite scrolling feeds
- aggressive notifications
- complex nested menus

---

# 22. Summary

The BarePitch UI interaction model is based on:
- calm visual hierarchy
- minimal interaction friction
- mobile-first operation
- fast event registration
- predictable behavior
- low cognitive load

The interface exists to support the match.

It must never compete with it.

---

# End
