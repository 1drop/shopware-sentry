{extends file="parent:frontend/index/index.tpl"}

{block name="frontend_index_header_javascript_jquery_lib"}
    {$smarty.block.parent}
    {* If sentry tracking of JS errors is enabled *}
    {if {config name='sentryLogJs'}}
        <script type="text/javascript">
            if (Raven) {
                Raven.config('{config name='sentryPublicDsn'}').install();
            }
        </script>
    {/if}
    {* If a PHP error occured and we want to collect additional feedback *}
    {if $sentryId}
        <script>
            if (Raven) {
                Raven.showReportDialog({
                    eventId: '{$sentryId}',
                    dsn: '{config name='sentryPublicDsn'}'
                });
            }
        </script>
    {/if}
{/block}
