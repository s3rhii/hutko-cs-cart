{*
    hutko Payment Gateway for CS-Cart
    This is an independent third-party payment integration for hutko and CS-Cart.
    It is not affiliated with, endorsed by or officially supported by hutko.
    It was developed using publicly available hutko API documentation.
    For merchant-account or transaction-support questions, contact hutko directly.
*}

<div class="alert alert-info">
    <strong>{__("hutko.third_party_notice_title")}:</strong>
    {__("hutko.third_party_disclaimer")}
</div>

<div class="control-group">
    <label class="control-label cm-required" for="hutko_merchant_id">Merchant ID:</label>
    <div class="controls">
        <input type="text"
               name="payment_data[processor_params][merchant_id]"
               id="hutko_merchant_id"
               value="{$processor_params.merchant_id|default:""}"
               class="input-text"
               autocomplete="off" />
        <p class="muted description">{__("hutko.merchant_id_tooltip")}</p>
    </div>
</div>

<div class="control-group">
    <label class="control-label cm-required" for="hutko_secret_key">Secret key:</label>
    <div class="controls">
        <input type="password"
               name="payment_data[processor_params][secret_key]"
               id="hutko_secret_key"
               value="{$processor_params.secret_key|default:""}"
               class="input-text"
               autocomplete="new-password" />
        <p class="muted description">{__("hutko.secret_key_tooltip")}</p>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="hutko_currency">{__("currency")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][currency]" id="hutko_currency">
            <option value="" {if !$processor_params.currency}selected="selected"{/if}>{__("hutko.use_order_currency")}</option>
            {foreach from=$currencies item="curr" key="code"}
                <option value="{$code}" {if $processor_params.currency == $code}selected="selected"{/if}>{$curr.description} ({$code})</option>
            {/foreach}
        </select>
    </div>
</div>

{assign var="statuses" value=$smarty.const.STATUSES_ORDER|fn_get_simple_statuses}

<div class="control-group">
    <label class="control-label" for="hutko_pending_status">{__("hutko.pending_order_status")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][pending_status]" id="hutko_pending_status">
            {foreach from=$statuses item="s" key="k"}
                <option value="{$k}" {if ($processor_params.pending_status == $k) || (!$processor_params.pending_status && $k == "O")}selected="selected"{/if}>{$s}</option>
            {/foreach}
        </select>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="hutko_paid_status">{__("hutko.paid_order_status")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][paid_status]" id="hutko_paid_status">
            {foreach from=$statuses item="s" key="k"}
                <option value="{$k}" {if ($processor_params.paid_status == $k) || (!$processor_params.paid_status && $k == "P")}selected="selected"{/if}>{$s}</option>
            {/foreach}
        </select>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="hutko_failed_status">{__("hutko.failed_order_status")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][failed_status]" id="hutko_failed_status">
            {foreach from=$statuses item="s" key="k"}
                <option value="{$k}" {if ($processor_params.failed_status == $k) || (!$processor_params.failed_status && $k == "F")}selected="selected"{/if}>{$s}</option>
            {/foreach}
        </select>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="hutko_logging">{__("hutko.enable_logging")}:</label>
    <div class="controls">
        <input type="hidden" name="payment_data[processor_params][logging]" value="N" />
        <input type="checkbox"
               name="payment_data[processor_params][logging]"
               id="hutko_logging"
               value="Y"
               {if $processor_params.logging == "Y"}checked="checked"{/if} />
    </div>
</div>
