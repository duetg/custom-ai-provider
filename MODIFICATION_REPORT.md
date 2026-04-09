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
- `custom_ai_save` (nonce action) → `duetgaicon_save`

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

## 5. Default API Values Removed

**Original behavior:** Plugin shipped with default OpenAI values pre-filled:
- Text Base URL: `https://api.openai.com/v1`
- Text Model: `gpt-4`
- Image Base URL: `https://api.openai.com/v1`
- Image Model: `dall-e-3`

**Changed to:** All default values are empty. Users must configure their own provider.

**Reason:** The reviewer stated we cannot endorse or pre-configure for any specific external service. Each user's AI provider configuration is their own choice.

**Changes in `src/Settings/Settings.php`:**
- Removed `DEFAULT_TEXT_BASE_URL`, `DEFAULT_TEXT_MODEL`, `DEFAULT_IMAGE_BASE_URL`, `DEFAULT_IMAGE_MODEL` constants
- `getTextBaseUrl()`, `getTextModel()`, `getImageBaseUrl()`, `getImageModel()` now return empty strings when not configured
- Admin UI now shows placeholder text instead of default values

**Changes in `src/Admin/Admin.php`:**
- Removed "Default value" notices from admin UI
- Added warning messages when settings are not configured
- Placeholder text instead of pre-filled defaults in input fields

---

## 6. Email Address Update

**Original:** `hello@duetg.com`
**Changed to:** `duetxg@gmail.com`

**Reason:** Correct author contact information.

---

## 7. Author URI

**Current:** `https://github.com/duetg/duetg-ai-connector`

**Status:** Already correct (was set properly from the beginning).

---

## Summary of Commits

| Commit | Description |
|--------|-------------|
| `869fbd2` | Rename main plugin file to match new slug |
| `a8b9fc2` | Update prefix and text domain to duetgaicon/duetg-ai-connector |
| `c156cb7` | Update plugin name in readme files to DuetG AI Connector |
| `899b91c` | Rename POT file to match new text domain |
| `6fb77c1` | Update email address in POT file |

---

## Files Changed Summary

| File | Change Type |
|------|-------------|
| `duetg-ai-connector.php` | Renamed from plugin.php, headers updated |
| `src/Settings/Settings.php` | Constants, option names, default values |
| `src/autoload.php` | Function names, constant names, log prefix |
| `src/Admin/Admin.php` | Nonce/action names, text domain, UI text |
| `src/Admin/TestPage.php` | JS handle, function calls, text domain |
| `src/Provider/CustomTextProvider.php` | Text domain |
| `src/Provider/CustomImageProvider.php` | Text domain |
| `src/Models/TextGeneration/CustomTextGenerationModel.php` | Function calls |
| `src/Models/ImageGeneration/CustomImageGenerationModel.php` | Function calls |
| `README.md` | Plugin name and description |
| `readme.txt` | Plugin name and description |
| `languages/duetg-ai-connector.pot` | Renamed, text domain updated, email updated |

---

## Compatibility Note

The plugin continues to use the WordPress AI Client registry API with provider names `custom_text` and `custom_image`. These provider IDs remain unchanged to maintain backward compatibility with existing user code that references these providers. The provider IDs are internal identifiers and do not conflict with the new unique prefix requirement.
