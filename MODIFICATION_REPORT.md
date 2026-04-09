# Modification Report: DuetG AI Connector

## Overview

This report details all changes made to the Custom AI Provider plugin in response to the WordPress Plugin Directory review. The plugin has been renamed to **DuetG AI Connector** (slug: `duetg-ai-connector`).

---

## 1. Plugin Name and Slug Change

**Original:** Custom AI Provider (slug: custom-ai-provider)
**Changed to:** DuetG AI Connector (slug: duetg-ai-connector)

**Reason:** The reviewer stated "Your plugin name is too generic" and "The prefix 'custom' is too common." The new name "DuetG AI Connector" is distinctive and clearly identifies the plugin with its developer brand while accurately describing its purpose.

**Files affected:**
- Main plugin file renamed: `plugin.php` → `duetg-ai-connector.php`
- Header updated: `Plugin Name: DuetG AI Connector`
- All internal references updated

---

## 2. Code Prefix Change

**Original prefix:** `custom_ai_`
**Changed to:** `duetgaicon_`

**Reason:** WordPress coding standards require a unique prefix of 4+ characters to avoid conflicts with other plugins. The prefix `custom` was deemed too generic. The new prefix `duetgaicon` combines the developer name with "ai" and "con" (connector) for clarity.

**Examples of changes:**
- `custom_ai_debug()` → `duetgaicon_debug()`
- `CUSTOM_AI_DEBUG` → `DUETGAICON_DEBUG`
- `CUSTOM_AI_ALLOW_LOCAL_URLS` → `DUETGAICON_ALLOW_LOCAL_URLS`
- `CUSTOM_AI_CHECK_DNS` → `DUETGAICON_CHECK_DNS`
- `custom_ai_save` (nonce action) → `duetgaicon_save`
- `custom_ai_test_action` → `duetgaicon_test_action`
- 5 filter/hook functions renamed: `custom_ai_preferred_*_filter` → `duetgaicon_preferred_*_filter`
- `custom_ai_provider_action_links` → `duetgaicon_provider_action_links`

---

## 3. Text Domain Change

**Original text domain:** `custom-ai-provider`
**Changed to:** `duetg-ai-connector`

**Reason:** The text domain should match the plugin slug for proper internationalization support.

**Files affected:**
- `duetg-ai-connector.php` - header text domain
- `src/Admin/Admin.php`
- `src/Admin/TestPage.php`
- `src/Provider/CustomTextProvider.php`
- `src/Provider/CustomImageProvider.php`
- `src/Models/TextGeneration/CustomTextGenerationModel.php`
- `src/Models/ImageGeneration/CustomImageGenerationModel.php`
- POT file renamed: `languages/custom-ai-provider.pot` → `languages/duetg-ai-connector.pot`

---

## 4. Settings Option Names

**Original pattern:** `connectors_ai_custom_text_*`, `connectors_ai_custom_image_*`
**Changed to:** `connectors_ai_duetgaicon_text_*`, `connectors_ai_duetgaicon_image_*`

**Reason:** Consistent prefix usage throughout the codebase to avoid option name collisions.

**Files affected:**
- `src/Settings/Settings.php` - all option get/set calls

---

## 5. Default API Values

**Original behavior:** Plugin shipped with default OpenAI values pre-filled (intentionally kept)

**Current behavior:** Plugin ships with default OpenAI values:
- Text Base URL: `https://api.openai.com/v1`
- Text Model: `gpt-4`
- Image Base URL: `https://api.openai.com/v1`
- Image Model: `dall-e-3`

**Reason:** The plugin is a "connector" to OpenAI-compatible APIs, and these defaults are provided as convenient starting points. Users must still configure their own API key. The defaults can be changed in the settings or overridden via constants.

**Changes in `src/Settings/Settings.php`:**
- Added `DEFAULT_TEXT_BASE_URL`, `DEFAULT_TEXT_MODEL`, `DEFAULT_IMAGE_BASE_URL`, `DEFAULT_IMAGE_MODEL` constants
- `register_setting()` defaults updated to use these constants
- `getTextBaseUrl()`, `getTextModel()`, `getImageBaseUrl()`, `getImageModel()` now use defaults as fallback

---

## 6. External Services Documentation

**Added:** `== External Services ==` chapter in `readme.txt`

**Reason:** WordPress Plugin Directory requires disclosure of any external services the plugin connects to.

**Content includes:**
- Description of the external service connection (user-configured OpenAI-compatible APIs)
- Data that is sent: prompt text, model configuration, and for vision features, image data
- When data is sent: when users submit text/image generation requests
- Links to Terms of Service and Privacy Policy for OpenAI
- Note for users to consult their provider's documentation

---

## 7. Email Address Update

**Original:** `hello@duetg.com`
**Changed to:** `duetxg@gmail.com`

**Reason:** Correct author contact information.

---

## 8. Author URI

**Current:** `https://github.com/duetg/duetg-ai-connector`

**Status:** Already correct.

---

## 9. Additional Fixes from Code Audit

| Issue | Description | Fix |
|-------|-------------|-----|
| R-01 | 5 functions still using `custom_ai_` prefix | Renamed to `duetgaicon_` |
| R-03 | Nonce action `custom_ai_test_action` | Changed to `duetgaicon_test_action` |
| R-04 | Constant `CUSTOM_AI_DEBUG` | Changed to `DUETGAICON_DEBUG` |
| R-05 | Constants `CUSTOM_AI_ALLOW_LOCAL_URLS`, `CUSTOM_AI_CHECK_DNS` | Changed to `DUETGAICON_*` |
| R-06 | TestPage.php referencing `plugin.php` | Changed to `duetg-ai-connector.php` |
| R-07 | TestPage.php header "Custom AI Provider" | Changed to "DuetG AI Connector" |
| R-08 | composer.json name | Changed to `duetg/duetg-ai-connector` |
| R-09/R-10 | readme files referencing old constant | Updated to `DUETGAICON_ALLOW_LOCAL_URLS` |

---

## Summary of Commits

| Commit | Description |
|--------|-------------|
| `869fbd2` | Rename main plugin file to match new slug |
| `a8b9fc2` | Update prefix and text domain to duetgaicon/duetg-ai-connector |
| `c156cb7` | Update plugin name in readme files to DuetG AI Connector |
| `899b91c` | Rename POT file to match new text domain |
| `6fb77c1` | Update email address in POT file |
| `dde4f0f` | Add modification report for reviewer |
| `2847f8a` | Complete audit fixes and restore default OpenAI API values |

---

## Files Changed Summary

| File | Change Type |
|------|-------------|
| `duetg-ai-connector.php` | Renamed from plugin.php, headers updated, function prefixes renamed |
| `src/Settings/Settings.php` | Constants added, option names updated, defaults restored |
| `src/autoload.php` | Function names, constant names, log prefix |
| `src/Admin/Admin.php` | Nonce/action names, text domain, UI text |
| `src/Admin/TestPage.php` | JS handle, function calls, nonce action, constant, file reference, header |
| `src/Provider/CustomTextProvider.php` | Text domain |
| `src/Provider/CustomImageProvider.php` | Text domain |
| `src/Models/TextGeneration/CustomTextGenerationModel.php` | Function calls |
| `src/Models/ImageGeneration/CustomImageGenerationModel.php` | Constants renamed |
| `README.md` | Plugin name, description, constant names |
| `readme.txt` | Plugin name, description, External services section, constant names |
| `composer.json` | Package name |
| `languages/duetg-ai-connector.pot` | Renamed, text domain updated, email updated |

---

## Compatibility Note

The plugin continues to use the WordPress AI Client registry API with provider names `custom_text` and `custom_image`. These provider IDs remain unchanged to maintain backward compatibility with existing user code that references these providers. The provider IDs are internal identifiers and do not conflict with the new unique prefix requirement.
