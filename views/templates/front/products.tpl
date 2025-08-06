<style>
    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    .product-card {
        border: 1px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: box-shadow 0.3s;
    }
    .product-card:hover {
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
    }
    .product-image {
        width: 100%;
        height: 200px;
        object-fit: contain;
        padding: 10px;
        background-color: #fff;
    }
    .product-content {
        padding: 15px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    .product-name {
        font-size: 1.1em;
        font-weight: bold;
        min-height: 60px;
    }
    .product-sku {
        font-size: 0.9em;
        color: #777;
    }
    .product-price {
        font-size: 1.2em;
        font-weight: bold;
        text-align: right;
        margin-top: auto;
    }
    .product-description-short {
        margin-top: 10px;
        font-size: 0.9em;
        color: #555;
    }
    .product-features {
        margin-top: 10px;
        font-size: 0.9em;
    }
    .product-features ul {
        padding-left: 20px;
        margin-bottom: 0;
    }
</style>

<div class="container">
    <h1>{l s='Catalogue des Produits Victron Energy' mod='ps_victronproducts'}</h1>
    
    <div class="product-grid">
        {foreach from=$products item=product}
            <div class="product-card">
                <a href="{$product.link}">
                    {if !empty($product.image_url)}
                        <img src="{$product.image_url}" alt="{$product.name|escape:'html':'UTF-8'}" class="product-image">
                    {else}
                        {* Vous pouvez mettre une image par défaut ici si vous le souhaitez *}
                        <img src="" alt="{$product.name|escape:'html':'UTF-8'}" class="product-image">
                    {/if}
                </a>
                <div class="product-content">
                    <h2 class="product-name">
                        <a href="{$product.link}">{$product.name|escape:'html':'UTF-8'}</a>
                    </h2>
                    <p class="product-sku">{l s='SKU:' mod='ps_victronproducts'} {$product.reference|escape:'html':'UTF-8'}</p>
                    
                    {if !empty($product.description_short)}
                        <div class="product-description-short">
                            {$product.description_short|strip_tags:'UTF-8'|truncate:120:'...'}
                        </div>
                    {/if}

                    {if !empty($product.features)}
                        <div class="product-features">
                            <strong>{l s='Caractéristiques' mod='ps_victronproducts'}:</strong>
                            <ul>
                                {foreach from=$product.features item=feature}
                                    {if isset($feature.name) && isset($feature.value)}
                                        <li><strong>{$feature.name|escape:'html':'UTF-8'}:</strong> {$feature.value|escape:'html':'UTF-8'}</li>
                                    {/if}
                                {/foreach}
                            </ul>
                        </div>
                    {/if}
                    
                    <div class="product-price">{$product.price|escape:'html':'UTF-8'} &euro;</div>
                </div>
            </div>
        {/foreach}
    </div>
</div>