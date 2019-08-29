<table width="100%" cellpadding="2" cellspacing="0" border="0" class="detailview_wrapper_table">
	<tr>
		<td class="detailview_wrapper_cell">
			{include file='Buttons_List.tpl'}
		</td>
	</tr>
</table>
<div class="slds-page-header" role="banner">
	<div class="slds-grid">
		<div class="slds-col slds-has-flexi-truncate">
			<div class="slds-media slds-no-space slds-grow">
				<div class="slds-media__figure">
					<svg aria-hidden="true" class="slds-icon slds-icon-standard-user">
						<use xlink:href="include/LD/assets/icons/utility-sprite/svg/symbols.svg#sync"></use>
					</svg>
				</div>
				<div class="slds-media__body">
					<h1 class="slds-page-header__title slds-m-right--small slds-align-middle slds-truncate"
						title="{$TITLE_MESSAGE}">{$TITLE_MESSAGE}</h1>
				</div>
			</div>
		</div>
	</div>
</div>
<br>
{if $ISADMIN}
{include 'applicationmessage.tpl'}
<form role="form" style="margin:0 100px;">
<input type="hidden" name="module" value="Utilities">
<input type="hidden" name="action" value="integration">
<input type="hidden" name="_op" value="setconfiggmp">
<div class="slds-form-element">
	<label class="slds-checkbox--toggle slds-grid">
	<span class="slds-form-element__label slds-m-bottom--none">{'_active'|@getTranslatedString:$MODULE}</span>
	<input type="checkbox" name="gmp_active" aria-describedby="toggle-desc" {if $isActive}checked{/if} />
	<span id="toggle-desc" class="slds-checkbox--faux_container" aria-live="assertive">
		<span class="slds-checkbox--faux"></span>
		<span class="slds-checkbox--on">{'LBL_ENABLED'|@getTranslatedString:'Settings'}</span>
		<span class="slds-checkbox--off">{'LBL_DISABLED'|@getTranslatedString:'Settings'}</span>
	</span>
	</label>
</div>
<div class="slds-form-element slds-m-top--small">
	<label class="slds-form-element__label" for="gid">{'gmp_gid'|@getTranslatedString:$MODULE}</label>
	<div class="slds-form-element__control">
		<input type="text" id="gid" name="gid" class="slds-input" value="{$gid}" />
	</div>
</div>
<div class="slds-form-element slds-m-top--small">
	<label class="slds-form-element__label" for="gversion">{'gmp_gversion'|@getTranslatedString:$MODULE}</label>
	<div class="slds-form-element__control">
		<input type="text" id="gversion" name="gversion" class="slds-input" value="{$gversion}" />
	</div>
</div>
<div class="slds-m-top--large">
	<button type="submit" class="slds-button slds-button--brand">{'LBL_SAVE_BUTTON_LABEL'|@getTranslatedString:$MODULE}</button>
</div>
</form>
{/if}