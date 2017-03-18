{extends file="parent:frontend/index/index.tpl"}

{block name="frontend_index_header_javascript_jquery_lib"}
    {$smarty.block.parent}
    {if {config name='logJs'}}
    <script type="text/javascript">
        if (Raven) {
            Raven.config('{config name='publicDsn'}').install();
        }
    </script>
    {/if}
{/block}
