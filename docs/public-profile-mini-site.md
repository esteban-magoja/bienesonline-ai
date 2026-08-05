# Public Profile Mini Site

## Goal

Extend the public user/realtor profile into a small public website while preserving the existing profile URLs:

- `/es/inmobiliaria/{username}`
- `/en/realtor/{username}`

The profile remains owned by one platform user. Team members are content records and do not receive platform accounts.

## MVP Scope

- Public profile hero with headline and optional cover image.
- Company description shown in the profile hero, reusing the existing `about` profile field as fallback.
- Services managed by the profile owner.
- Team members managed as simple profile-owned records.
- Featured properties using existing `is_featured` listings, with recent listings as fallback.
- Existing property catalogue and filters.
- Service areas derived from active listings.
- Social links, website, office hours, and contact visibility settings.
- Spanish and English content for editable text fields.
- SEO metadata and JSON-LD for public profiles.

The public contact form and profile leads were intentionally postponed and are not part of the current MVP.

## Data Model

### `user_profile_settings`

One row per user. Stores headline, cover image, website, social links, office hours, and public visibility flags for email, phone, and address.

### `user_profile_services`

Many rows per user. Stores translated name/description, icon, order, and active state.

### `user_profile_members`

Many rows per user. Stores name, role, photo, translated bio, specialties, areas, contact details, visibility flags, and order. These are not `users`.

The `user_profile_leads` migration was created during an earlier implementation attempt and the table may exist in development databases, but the model, route, UI, and notification were removed. Do not use it unless the contact feature is deliberately resumed.

## Implementation Order

1. Migrations, models, relationships, casts, and factories where useful.
2. Owner settings panel for public profile content.
3. Owner CRUD for services and team members.
4. Public profile sections and privacy-aware contact display.
5. SEO structured data, translations, feature tests, and performance cleanup.

## Completed

- Added `user_profile_settings`, `user_profile_services`, and `user_profile_members` models, migrations, factories, and user relationships.
- Added authenticated settings pages:
  - `/settings/public-profile`
  - `/settings/services`
  - `/settings/team`
- Added Spanish and English translations for the mini-site settings UI.
- Added services with a safe Phosphor icon selector and explicit display-order help.
- Added internal team members without creating platform users.
- Added public hero, cover image, services, team, featured properties, social links, office hours, and privacy-aware contact details.
- Moved breadcrumbs above the profile hero.
- Replaced the mini-site text links with responsive visual navigation cards.
- Removed the duplicated public “Quiénes somos” section.
- Added a shared `x-property-profile-card` component used by featured and regular profile listings.
- The shared property card displays price, property type, transaction type, location, bedrooms, bathrooms, and area.
- Added JSON-LD profile metadata and retained existing canonical/hreflang behavior.
- Added focused Pest coverage in `tests/Feature/PublicProfileMiniSiteTest.php` and `tests/Feature/UserProfileTest.php`.

## Decisions

- Keep the public site on the current profile route in the MVP; use anchor navigation before adding indexable subroutes.
- Use the existing avatar as the logo fallback and add a separate cover image.
- Use `is_featured` for the initial featured property selection.
- Keep one shared visual design; no user-selected themes in the MVP.
- Team members are content records, not platform users.
- Public contact form and `/settings/leads` are postponed; direct email, phone, and WhatsApp links remain available according to privacy settings.

## Security and Privacy

- Every owner-side write must verify `user_id === auth()->id()`.
- Public email, phone, and address visibility must be configurable.
- Uploaded images require image MIME/type and size validation.
- User-entered rich content must not be rendered as trusted HTML in the MVP.

## Verification

- Run focused profile and mini-site Pest tests after each feature group.
- Run `php artisan migrate` in the configured development database.
- Run `php artisan test --compact` before considering the MVP complete.

The last focused verification completed successfully:

- `php artisan test --compact tests/Feature/PublicProfileMiniSiteTest.php tests/Feature/UserProfileTest.php`
- 9 tests, 46 assertions.
- `php artisan view:cache` completed successfully.

The full suite still contains unrelated legacy route-test failures for `/`, `/about`, and `/profile/admin`; these were present before the mini-site work and should be handled separately.

## Next Session

1. Inspect the rendered profile at `/es/inmobiliaria/casas2inmobiliaria` on desktop and mobile.
2. Validate service and team CRUD manually with the dashboard test user.
3. Decide whether to keep or remove the historical `user_profile_leads` table through a deliberate migration.
4. Consider extracting the remaining profile filter form into a reusable component.
5. Consider adding a dedicated public profile preview link inside Settings.
