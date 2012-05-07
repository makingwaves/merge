{def $used_languages = array()
     $can_merge = count( $node_list )|eq( 2 )
     $contains_class = ''
     $relation_count = 0
     $reverse_count = 0}

<h1>{'Merge content objects'|i18n( 'design/merge' )}</h1>

<p>{'The list must contain exactly two content objects of the same content class to allow merging.'|i18n( 'design/merge' )}</p>
<p>{'A merge may take considerable time, depending on number of languages and relations.'|i18n( 'design/merge' )}</p>

<form name="MergeSelection" action={'content/action'|ezurl()} method="POST">

{if count( $node_list )|gt( 0 )}
    <table border="1">
    <tr>
        <th><img src={'toggle-button-16x16.gif'|ezimage()} width="16" height="16" alt="{'Invert selection.'|i18n( 'design/admin/node/view/full' )}" title="{'Invert selection.'|i18n( 'design/admin/node/view/full' )}" onclick="javascript:ezjs_toggleCheckboxes( document.MergeSelection, 'RemoveNode[]' ); return false;"/></th>
        <th>{'Merge to...'|i18n( 'design/merge' )}</th>
        {foreach $language_list as $language}
            <th><img src="{$language|flag_icon()}" width="18" height="12" alt="{$language}" title="{$language}" /></th>
        {/foreach}
    </tr>
    {foreach $node_list as $node_translations}
        <tr>
            <td>
                {foreach $node_translations as $key => $node}
                    {if $node}
                        <input type="checkbox" name="RemoveNode[]" value="{$node.node_id}" />
                        {break}
                    {/if}
                {/foreach}
            </td>
            <td>
                {foreach $node_translations as $key => $node}
                    {if $node}
                        {set $relation_count = fetch( 'content', 'related_objects_count',
                                                            hash( 'object_id', $node.contentobject_id,
                                                                'all_relations', true(),
                                                                'ignore_visibility', true() ) )
                             $reverse_count = fetch( 'content', 'reverse_related_objects_count',
                                                            hash( 'object_id', $node.contentobject_id,
                                                                'all_relations', true(),
                                                                'ignore_visibility', true() ) )}
                        <input type="radio" name="MergeNodeMaster" value="{$node.node_id}"{if $node.node_id|eq( $merge_node_master )} checked="checked"{/if} />
                        <a href={$node.url_alias|ezurl()}>{$node.name}</a>
                        (<a href={concat( '/class/view/', $node.object.contentclass_id )|ezurl()}>{$node.class_name}</a>)
                        [{$relation_count}/{$reverse_count}]
                        {break}
                    {/if}
                {/foreach}
            </td>
            {foreach $language_list as $key => $language}
                <td>
                    {if is_set( $node_translations.$language )}
                        <input type="radio" name="MergeTranslation_{$key}" value="{$language}_{$node_translations.$language.node_id}"{if $translation_map.$language|eq( $node_translations.$language.node_id )} checked="checked"{/if} />
                        {$node_translations.$language.name}
                        {if $used_languages|contains( $language )|not()}
                            {set $used_languages = $used_languages|append( $language )}
                        {/if}
                        {* Make sure all selected objects are of same class *}
                        {if $contains_class|eq( '' )}
                            {set $contains_class = $node_translations.$language.class_identifier}
                        {elseif $contains_class|ne( $node_translations.$language.class_identifier )}
                            {set $can_merge = false()}
                        {/if}
                    {/if}
                </td>
            {/foreach}
        </tr>
    {/foreach}
    </table>

    <br />
    <input type="submit" name="RemoveObjects" value="{'Remove selected objects from list'|i18n( 'design/merge' )}" />
{/if}
<input type="submit" name="MergeBrowse" value="{'Add objects to list'|i18n( 'design/merge' )}" />

<br />
<br />
<input type="submit" name="MergeAction" value="{'Merge listed content objects'|i18n( 'design/merge' )}"{if $can_merge|not()} disabled="disabled"{/if} /> ({'this can not be undone!'|i18n( 'design/merge' )})

<input type="hidden" name="ContentObjectID" value="2561" />
</form>
