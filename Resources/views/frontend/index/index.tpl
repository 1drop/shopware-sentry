{extends file="parent:frontend/index/index.tpl"}

{block name="frontend_index_header_javascript_jquery_lib"}
    {$smarty.block.parent}
    {block name="frontend_index_header_javascript_jquery_lib_sentry"}
        {* If sentry tracking of JS errors is enabled *}
        {if {config name='sentryLogJs'}}
            {if $theme.asyncJavascriptLoading}
                <script type="text/javascript">
                    document.asyncReady(function() {
                        if (Raven) {
                            Raven.config('{config name='sentryPublicDsn'}').install();
                        }
                    });
                </script>
            {else}
                <script type="text/javascript">
                    if (Raven) {
                        Raven.config('{config name='sentryPublicDsn'}').install();
                    }
                </script>
            {/if}
        {/if}
        {* If a PHP error occured and we want to collect additional feedback *}
        {if $sentryId}
            {if $theme.asyncJavascriptLoading}
                <script type="text/javascript">
                    document.asyncReady(function() {
                        if (Raven) {
                            Raven.showReportDialog({
                                eventId: '{$sentryId}',
                                dsn: '{config name='sentryPublicDsn'}'
                            });
                        }
                    });
                </script>
            {else}
                <script type="text/javascript">
                    if (Raven) {
                        Raven.showReportDialog({
                            eventId: '{$sentryId}',
                            dsn: '{config name='sentryPublicDsn'}'
                        });
                    }
                </script>
            {/if}
        {/if}
    {/block}
{/block}
