# Translation Guide

## ✅ Translation Ready

Mail Chronicle is fully translation-ready and follows WordPress i18n best practices.

## Translation Status

- **Text Domain**: `mail-chronicle`
- **Domain Path**: `/languages`
- **POT File**: `languages/mail-chronicle.pot` (444 strings)
- **All strings wrapped**: ✅ PHP and JavaScript

## How to Translate

### Method 1: Using Loco Translate (Recommended for WordPress Users)

1. Install the [Loco Translate](https://wordpress.org/plugins/loco-translate/) plugin
2. Go to **Loco Translate → Plugins**
3. Select **Mail Chronicle**
4. Click **+ New language**
5. Choose your language
6. Click **Start translating**
7. Translate all strings
8. Save

### Method 2: Using Poedit (Recommended for Translators)

1. Download and install [Poedit](https://poedit.net/)
2. Open Poedit
3. Click **File → New from POT/PO file**
4. Select `wp-content/plugins/mail-chronicle/languages/mail-chronicle.pot`
5. Choose your language
6. Translate all strings
7. Save as `mail-chronicle-{locale}.po` (e.g., `mail-chronicle-de_DE.po`)
8. Poedit will automatically generate the `.mo` file
9. Upload both `.po` and `.mo` files to `wp-content/plugins/mail-chronicle/languages/`

### Method 3: Using WP-CLI

```bash
# Generate POT file (already done)
wp i18n make-pot . languages/mail-chronicle.pot --domain=mail-chronicle

# Create a new translation
wp i18n make-po languages/mail-chronicle.pot languages/mail-chronicle-de_DE.po

# Compile PO to MO
msgfmt languages/mail-chronicle-de_DE.po -o languages/mail-chronicle-de_DE.mo
```

### Method 4: Using translate.wordpress.org (For Official Translations)

If Mail Chronicle is published on WordPress.org, you can contribute translations at:
https://translate.wordpress.org/projects/wp-plugins/mail-chronicle/

## Translation Files

The plugin expects translation files in this format:

```
languages/
├── mail-chronicle.pot          # Template file (do not edit)
├── mail-chronicle-de_DE.po     # German translation (editable)
├── mail-chronicle-de_DE.mo     # German compiled (auto-generated)
├── mail-chronicle-fr_FR.po     # French translation
├── mail-chronicle-fr_FR.mo     # French compiled
└── ...
```

## Common Locale Codes

| Language | Locale Code |
|----------|-------------|
| German | `de_DE` |
| French | `fr_FR` |
| Spanish | `es_ES` |
| Italian | `it_IT` |
| Portuguese (Brazil) | `pt_BR` |
| Portuguese (Portugal) | `pt_PT` |
| Dutch | `nl_NL` |
| Russian | `ru_RU` |
| Japanese | `ja` |
| Chinese (Simplified) | `zh_CN` |
| Chinese (Traditional) | `zh_TW` |

## Translation Context

### Key Areas to Translate

1. **Empty State Messages** (11 strings)
   - "No emails logged yet"
   - "Enable Logging"
   - "Send a Test Email"
   - etc.

2. **Email Logs Interface** (30+ strings)
   - Table headers
   - Filter labels
   - Button text
   - Status messages

3. **Settings Page** (20+ strings)
   - Setting labels
   - Help text
   - Validation messages

4. **Email Details Modal** (15+ strings)
   - Field labels
   - Event types
   - Action buttons

## Testing Translations

1. Install your translation files in `languages/`
2. Change WordPress language in **Settings → General → Site Language**
3. Visit the Mail Chronicle pages to verify translations
4. Check both admin interface and empty states

## Regenerating POT File

If you add new translatable strings to the code:

```bash
cd wp-content/plugins/mail-chronicle
wp i18n make-pot . languages/mail-chronicle.pot --domain=mail-chronicle
```

This will update the POT file with new strings.

## Best Practices for Translators

1. **Keep it concise**: UI text should be short and clear
2. **Maintain tone**: Professional but friendly
3. **Test in context**: See how translations look in the actual interface
4. **Use proper terminology**: Follow WordPress translation glossary for your language
5. **Preserve placeholders**: Keep `%s`, `%d`, etc. in the same position

## Contributing Translations

We welcome translation contributions! To contribute:

1. Fork the repository
2. Create your translation files
3. Test thoroughly
4. Submit a pull request

Or email translations to: miroslav@balan.at

## Support

For translation questions or issues:
- GitHub Issues: https://github.com/your-repo/mail-chronicle/issues
- Email: miroslav@balan.at

Thank you for helping make Mail Chronicle accessible to users worldwide! 🌍

