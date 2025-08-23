{*
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2018 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div class="victron-products">
    <h2>{l s='Our Victron Products' mod='ps_victronproducts'}</h2>
    <p class="update-info">
        {l s='Last update:' mod='ps_victronproducts'} 
        {$last_update|date_format:"%d/%m/%Y %H:%M"}
    </p>

    <div class="products-grid">
        {foreach from=$products item=product}
            <div class="product-item">
                <h3>{$product.name}</h3>
                {if isset($product.image)}
                    <img src="{$product.image}" alt="{$product.name}">
                {/if}
                <div class="price">{$product.price}</div>
                <p>{$product.description}</p>
            </div>
        {/foreach}
    </div>
</div>
