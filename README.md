# Gravity Forms Electorate Lookup
A Wordpress plugin that creates a new custom field allowing users to look up their street address to determine their Federal Electorate, State Electorate, or Local Government Area.

# How to use this plugin: 
1. Set the API key in Gravity forms settings:
Enter your Google API key via the "Electorate Lookup" settings tab. NB: This key must have places & geocoding APIs enabled.

2. Add a lookup field in the form editor:
This plugin creates a new field type in Gravity Forms called "Electorate Lookup", which can be included in all forms.
This custom field type includes settings for:
- Lookup type: CED (Federal) | SED (State) | LGA (Local Government)
- Optional state restriction: Allows restriction to single or multiple states/territories.

3. Save form. Lookup should appear as a single-line autocomplete text box.

# How it works:
1. User enters address, Google autocomplete addresses are suggested via Places api.
2. User selects their formatted address, which is then geocoded via Geocoding API.
3. Longitude & latitude coords are sent to appropriate ABS (Australian Bureau of Statistics) api end point (based on lookup type) to determine which electoral boundary this address falls within.
4. Address is returned & set as the value of the lookup field. This value can then be used in live merge tags (eg. via Populate Anything plugin), for lookups (to return MP names or emails from your own data sets) or end screen results.
