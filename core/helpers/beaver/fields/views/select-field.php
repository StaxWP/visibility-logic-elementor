<#
var field = data.field,
	name = data.name,
	value = data.value,
	settings = data.settings,
	preview = data.preview,
	default_val = ( 'undefined' != typeof field.default && '' != field.default )
	selected = '';
#>

<input type="text" name="{{data.name}}" value="{{data.value}}" />
