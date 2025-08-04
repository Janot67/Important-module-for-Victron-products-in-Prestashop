<div class="panel">
    <div class="panel-heading">
        <i class="icon-refresh"></i> {l s='Product Synchronization' mod='ps_victronproducts'}
    </div>

    <div class="alert alert-info">{l s='The synchronization is running in the background. Please do not close this window.' mod='ps_victronproducts'}</div>
    
    <div class="progress">
        <div id="victron-progress-bar" class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
            <span id="victron-progress-label">0%</span>
        </div>
    </div>
    
    <div id="victron-sync-log" style="height: 200px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px; margin-top: 15px; background-color: #f5f5f5;">
        <p>{l s='Starting synchronization...' mod='ps_victronproducts'}</p>
    </div>
    
    <div class="panel-footer">
        <a href="{$link->getAdminLink('AdminModules')|escape:'html':'UTF-8'}&configure={$module_name|escape:'html':'UTF-8'}" class="btn btn-default">
            <i class="process-icon-cancel"></i> {l s='Back to module configuration' mod='ps_victronproducts'}
        </a>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function() {
    var ajaxUrl = '{$ajax_url|escape:'html':'UTF-8'}';
    var batchSize = {$batch_size|intval};
    var totalProducts = 0;
    var processedProducts = 0;
    var tmpFile = '';

    function log(message) {
        $('#victron-sync-log').append('<p>' + new Date().toLocaleTimeString() + ': ' + message + '</p>').scrollTop($('#victron-sync-log')[0].scrollHeight);
    }

    function updateProgressBar() {
        var percentage = totalProducts > 0 ? Math.round((processedProducts / totalProducts) * 100) : 0;
        $('#victron-progress-bar').css('width', percentage + '%').attr('aria-valuenow', percentage);
        $('#victron-progress-label').text(percentage + '% (' + processedProducts + ' / ' + totalProducts + ')');
    }

    function processBatch(offset) {
        log('Processing batch starting at product ' + (offset + 1) + '...');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                step: 'process_batch',
                offset: offset,
                tmp_file: tmpFile
            },
            success: function(response) {
                if (response.error) {
                    log('<strong>Error:</strong> ' + response.error);
                    return;
                }
                processedProducts += response.processed_count;
                updateProgressBar();

                if (processedProducts < totalProducts) {
                    processBatch(processedProducts);
                } else {
                    log('All products processed. Cleaning up...');
                    cleanup();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                log('<strong>Critical Error:</strong> The server returned an error. Please check the browser console or server logs. Status: ' + textStatus + ', Error: ' + errorThrown);
            }
        });
    }

    function cleanup() {
         $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                step: 'cleanup',
                tmp_file: tmpFile
            },
            success: function(response) {
                log('<strong>Synchronization complete!</strong>');
                $('#victron-progress-bar').removeClass('active').addClass('progress-bar-success');
            },
            error: function() {
                log('<strong>Error:</strong> Cleanup failed.');
            }
        });
    }

    // Start the process
    log('Preparing synchronization, please wait...');
    $.ajax({
        url: ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: {
            step: 'prepare'
        },
        success: function(response) {
            if (response.error) {
                log('<strong>Error:</strong> ' + response.error);
                return;
            }
            totalProducts = response.total_products;
            tmpFile = response.tmp_file;
            log('Preparation complete. ' + totalProducts + ' products to import.');
            updateProgressBar();
            if (totalProducts > 0) {
                processBatch(0);
            } else {
                log('No products to import.');
                cleanup();
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            log('<strong>Critical Error:</strong> Could not prepare the synchronization. Status: ' + textStatus + ', Error: ' + errorThrown);
        }
    });
});
</script>