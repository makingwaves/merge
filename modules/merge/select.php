<?php

$http = eZHTTPTool::instance();
$Module = $Params['Module'];

$selected_array = array();
$merge_node_master = 0;
$translation_map = array();

if ( $http->hasSessionVariable( 'MergeNodeSelectionList' ) )
{
    $selected_array = $http->sessionVariable( 'MergeNodeSelectionList' );
}
if ( $http->hasSessionVariable( 'MergeNodeMaster' ) )
{
    $merge_node_master = $http->sessionVariable( 'MergeNodeMaster' );
}
if ( $http->hasSessionVariable( 'MergeObjectTranslationMap' ) )
{
    $translation_map = $http->sessionVariable( 'MergeObjectTranslationMap' );
}

// Update master node
if ( $http->hasPostVariable( 'MergeNodeMaster' ) )
{
    $merge_node_master = $http->postVariable( 'MergeNodeMaster' );
    $http->setSessionVariable( 'MergeNodeMaster', $merge_node_master );
}

// Get any new candidate nodes to merge
if ( $http->hasPostVariable( 'BrowseActionName' ) AND $http->postVariable( 'BrowseActionName' ) == 'MergeObjects' )
{
    if ( $http->hasPostVariable( 'SelectedNodeIDArray' ) )
    {
        $selected_array = array_merge( $selected_array, $http->postVariable( 'SelectedNodeIDArray' ) );
    }
    $http->setSessionVariable( 'MergeNodeSelectionList', $selected_array );
    $Module->redirectTo( '/merge/select' );
}

// Get a list of objects selected, and their languages
$tpl = eZTemplate::factory();
$node_list = array();
$language_list = array();
foreach ( $selected_array as $key => $node_id )
{
    $node = eZFunctionHandler::execute( 'content', 'node', array( 'node_id' => $node_id ) );
    if ( $node )
    {
        if ( empty( $merge_node_master ) )
        {
            $merge_node_master = $node_id;
            $http->setSessionVariable( 'MergeNodeMaster', $merge_node_master );
        }

        $object = $node->attribute( 'object' );
        $languages = $object->attribute( 'available_languages' );
        foreach ( $languages as $language )
        {
            if ( !in_array( $language, $language_list ) )
            {
                $language_list[] = $language;
            }
        }

        $update_translation_map = false;
        foreach ( $language_list as $language )
        {
            $node = eZFunctionHandler::execute( 'content', 'node', array( 'node_id' => $node_id,
                                                                        'language_code' => $language ) );
            if ( $node AND in_array( $language, $node->attribute( 'object' )->attribute( 'available_languages' ) ) )
                $node_list[$node_id][$language] = $node;
            // Set default values for translation map
            if ( !isset( $translation_map[$language] ) )
            {
                $translation_map[$language] = $node_id;
                $update_translation_map = true;
            }
        }
        if ( $update_translation_map )
        {
            $http->setSessionVariable( 'MergeObjectTranslationMap', $translation_map );
        }
    }
    else
    {
         unset( $selected_array[$key] );
         $http->setSessionVariable( 'MergeNodeSelectionList', array_values( $selected_array ) );
    }
}

$tpl->setVariable( 'node_list', $node_list );
$tpl->setVariable( 'merge_node_master', $merge_node_master );
$tpl->setVariable( 'translation_map', $translation_map );
$tpl->setVariable( 'language_list', $language_list );

$Result['content'] = $tpl->fetch( 'design:merge/select.tpl' );
$Result['path'] = array( array( 'url' => false,
                                'text' => ezpI18n::tr( 'kernel/content', 'Merge' ) ),
                         array( 'url' => false,
                                'text' => ezpI18n::tr( 'kernel/content', 'Select class' ) ) );

?>
