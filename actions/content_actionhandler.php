<?php

function merge_ContentActionHandler( &$module, &$http, &$objectID )
{
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
        if ( $merge_node_master != $http->postVariable( 'MergeNodeMaster' ) )
        {
            $merge_node_master = $http->postVariable( 'MergeNodeMaster' );
            $http->setSessionVariable( 'MergeNodeMaster', $merge_node_master );
        }

        // Update translation map
        $count = 0;
        while ( $http->hasPostVariable( 'MergeTranslation_' . $count ) )
        {
            $values = explode( '_', $http->postVariable( 'MergeTranslation_' . $count ) );
            $translation_map[$values[0]] = $values[1];
            $count++;
        }
        $http->setSessionVariable( 'MergeObjectTranslationMap', $translation_map );
    }


    // Remove selected nodes
    if ( $http->hasPostVariable( 'RemoveObjects' ) )
    {
        $remove_list = array();
        if ( $http->hasPostVariable( 'RemoveNode' ) )
        {
            $remove_list = $http->postVariable( 'RemoveNode' );
            $selected_array = array_values( array_diff( $selected_array, $remove_list ) );
            $http->setSessionVariable( 'MergeNodeSelectionList', $selected_array );

            // Check master node
            if ( !in_array( $merge_node_master, $selected_array ) )
            {
                $merge_node_master = 0;
                $http->setSessionVariable( 'MergeNodeMaster', 0 );
            }

            // Remove any related selected translations
            $update_translation_map = false;
            foreach ( $translation_map as $language => $node_id )
            {
                if ( in_array( $node_id, $remove_list ) )
                {
                    unset( $translation_map[$language] );
                    $update_translation_map = true;
                }
            }
            if ( $update_translation_map )
            {
                $http->setSessionVariable( 'MergeObjectTranslationMap', $translation_map );
            }
        }
        $module->redirectTo( '/merge/select' );
    }


    // Go to browse module to choose nodes to merge
    if ( $http->hasPostVariable( 'MergeBrowse' ) )
    {
        $ini = eZINI::instance( 'merge.ini' );
        $class_list = $ini->variable( 'MergeSettings', 'ClassList' );
        $start_node_id = $ini->variable( 'MergeSettings', 'BrowseNodeID' );
        eZContentBrowse::browse( array( 'action_name' => 'MergeObjects',
                                        'description_template' => 'design:content/browse_placement.tpl',
                                        'ignore_nodes_select' => $selected_array,
                                        'ignore_nodes_click'  => array(),
                                        'selection' => 'radio',
                                        'class_array' => $class_list,
                                        'start_node' => $start_node_id,
                                        'from_page' => '/merge/select' ),
                                 $module );
    }


    // Actual merge operation
    if ( $http->hasPostVariable( 'MergeAction' ) )
    {
        if ( count( $selected_array ) != 2 )
        {
            $module->redirectTo( '/merge/select' );
        }
        // Set up correct order according to selected master
        if ( $selected_array[1] == $merge_node_master )
        {
            $selected_array = array_reverse( $selected_array );
        }
        // Fetch objects to merge
        $mnode1 = eZFunctionHandler::execute( 'content', 'node', array( 'node_id' => $selected_array[0] ) );
        $mnode2 = eZFunctionHandler::execute( 'content', 'node', array( 'node_id' => $selected_array[1] ) );
        $mobject1 = $mnode1->attribute( 'object' );
        $mobject2 = $mnode2->attribute( 'object' );
        // Do sanity check
        if ( $mobject1->attribute( 'contentclass_id' ) != $mobject2->attribute( 'contentclass_id' ) )
        {
            $module->redirectTo( '/merge/select' );
        }

        $db = eZDB::instance();
        $db->begin();
        foreach ( $translation_map as $language => $node_id )
        {
            $object1 = $object2 = null;
            $node1 = eZFunctionHandler::execute( 'content', 'node', array( 'node_id' => $selected_array[0],
                                                                        'language_code' => $language ) );
            $node2 = eZFunctionHandler::execute( 'content', 'node', array( 'node_id' => $selected_array[1],
                                                                        'language_code' => $language ) );

            // Make sure we get correct language
            if ( $node1 )
            {
                $object1 = $node1->attribute( 'object' );
                if ( !in_array( $language, $object1->attribute( 'available_languages' ) ) )
                {
                    $object1 = $mobject1;
                }
            }
            else
                $object1 = $mobject1;

            if ( $node2 )
            {
                $object2 = $node2->attribute( 'object' );
                if ( !in_array( $language, $object2->attribute( 'available_languages' ) ) )
                    $object2 = null;
            }

            // Copy language in specified direction, if it exist in both objects
            $use_object1_values = ( $node_id == $selected_array[0] );
            if ( $object1 AND $object2 )
            {
                // Need to redirect node 2 current url alias to avoid added "2" in the new url alias of merged object
                $urlalias_array = eZURLAliasML::fetchByPath( $node2->attribute( 'url_alias' ) );
                foreach ( $urlalias_array as $urlalias )
                {
                    $urlalias = eZURLAliasML::fetchObject(
                        eZURLAliasML::definition(),
                        null,
                        array(
                            'id' => $urlalias->attribute( 'id' ),
                            'lang_mask' => $urlalias->attribute( 'lang_mask' )
                        )
                    );
                    $urlalias->setAttribute( 'action', 'eznode:' . $mnode1->attribute( 'node_id' ) );
                    $urlalias->store();
                }

                doContentObjectMerge($object1, $object2, $language, $use_object1_values);
            }
        }

        $main_node_id1 = $mobject1->attribute( 'main_node_id' );
        foreach ( $mobject2->attribute( 'assigned_nodes' ) as $node2 )
        {
            // Move any children of object2 nodes to object1 main node
            $children2 = eZFunctionHandler::execute( 'content', 'list', array( 'parent_node_id' => $node2->attribute( 'node_id' ) ) );
            foreach ( $children2 as $child2 )
            {
                eZContentObjectTreeNodeOperations::move( $child2->attribute( 'node_id' ), $main_node_id1 );
            }
        }

        // Delete object2
        $mobject2->purge();

        $db->commit();
//        $db->rollback(); // For debugging

        // Clean up session variables
        $http->removeSessionVariable( 'MergeNodeSelectionList' );
        $http->removeSessionVariable( 'MergeNodeMaster' );
        $http->removeSessionVariable( 'MergeObjectTranslationMap' );

        $module->redirectTo( $mnode1->attribute( 'url_alias' ) );
    }
}

function doContentObjectMerge( $object1, $object2, $language, $use_object1_values )
{
    // Merging objects based on selected translations
    $new_version = mergeObject2ToObject1( $object1, $object2, $language, $use_object1_values );

    // Update reverse related objects with new object relation
    updateReverseRelatedObjects( $object2, $object1 );
}

function mergeObject2ToObject1( $object1, $object2, $language, $use_object1_values )
{
    $new_version1 = $object1->createNewVersionIn( $language );
    $new_version1->setAttribute( 'modified', time() );
    $new_version1->store();

    $new_attributes1 = $new_version1->contentObjectAttributes();
    $datamap2 = $object2->datamap();

    // Find any object relation list attributes in objects
    foreach ( $new_attributes1 as $attribute1 )
    {
        $identifier = $attribute1->attribute( 'contentclass_attribute_identifier' );
        switch ( $attribute1->attribute( 'data_type_string' ) )
        {
            case 'ezobjectrelationlist':
                if ( $datamap2[$identifier]->attribute( 'has_content' ) )
                {
                    // Merge object relations from the two objects
                    $list1 = $attribute1->attribute( 'has_content' ) ? explode( '-', $attribute1->toString() ) : array();
                    $list2 = explode( '-', $datamap2[$identifier]->toString() );
                    $attribute1->fromString( implode( '-', array_unique( array_merge( $list1, $list2 ) ) ) );
                    $attribute1->store();
                }
                break;
            case 'ezkeyword':
                if ( $datamap2[$identifier]->attribute( 'has_content' ) )
                {
                    // Merge keywords from the two objects
                    $list1 = explode( ',', $attribute1->toString() );
                    $list2 = explode( ',', $datamap2[$identifier]->toString() );
                    $attribute1->fromString( implode( ',', array_unique( array_merge( $list1, $list2 ) ) ) );
                    $attribute1->store();
                }
                break;
            default:
                if ( !$use_object1_values )
                {
                    // Copy data from object2 to object1 for this language
                    $value = $datamap2[$identifier]->toString();
                    $attribute1->fromString( $value );
                    $attribute1->store();
                }
                break;
        }
    }
    // Publish new version of object1
    $operationResult = eZOperationHandler::execute( 'content', 'publish', 
                            array( 'object_id' => $object1->attribute( 'id' ),
                                    'version' => $new_version1->attribute( 'version' ) ) );

    eZContentCacheManager::clearObjectViewCache( $object1->attribute( 'id' ), $new_version1->attribute( 'version' ) );
}


function updateReverseRelatedObjects( $related_object, $new_related_object )
{
    $related_object_id = $related_object->attribute( 'id' );
    $new_related_object_id = $new_related_object->attribute( 'id' );
    $reverse_related_list = eZFunctionHandler::execute( 'content', 'reverse_related_objects', 
                            array( 'object_id' => $related_object_id,
                                    'all_relations' => true,
                                    'group_by_attribute' => true,
                                    'as_object' => true ) );

    foreach ( $reverse_related_list as $attribute_id => $reverse_related_sublist )
    {
        foreach ( $reverse_related_sublist as $reverse_related_object )
        {
            // To get the different languages of the related object, we need to go through a node fetch
            $main_node_id = $reverse_related_object->attribute( 'main_node_id' );
            $language_list = $reverse_related_object->attribute( 'available_languages' );
            foreach ( $language_list as $language )
            {
                $tmp_node = eZFunctionHandler::execute( 'content', 'node', array( 'node_id' => $main_node_id,
                                                                            'language_code' => $language ) );
                $reverse_related_object = $tmp_node->attribute( 'object' );
                $new_version = $reverse_related_object->createNewVersionIn( $language );
                $new_version->setAttribute( 'modified', time() );
                $new_version->store();

                $new_attributes = $new_version->contentObjectAttributes();
                foreach ( $new_attributes as $reverse_attribute )
                {
                    if ( empty( $attribute_id ) OR $reverse_attribute->attribute( 'contentclassattribute_id' ) == $attribute_id )
                    {
                        switch ( $reverse_attribute->attribute( 'data_type_string' ) )
                        {
                            case 'ezobjectrelationlist':
                                $old_list = $reverse_attribute->toString();
                                $list = explode( '-', $old_list );
                                foreach ( $list as $key => $object_id )
                                {
                                    if ( $object_id == $related_object_id )
                                    {
                                        $list[$key] = $new_related_object_id;
                                    }
                                }
                                $list = implode( '-', array_unique( $list ) );
                                if ( $old_list != $list )
                                {
                                    $reverse_attribute->fromString( $list );
                                    $reverse_attribute->store();
                                }
                                break;
                            case 'ezobjectrelation':
                                $old_relation = $reverse_attribute->toString();
                                if ( $old_relation != $new_related_object_id )
                                {
                                    $reverse_attribute->fromString( $new_related_object_id );
                                    $reverse_attribute->store();
                                }
                                break;
                            case 'ezxmltext':
                                $old_xml = $reverse_attribute->toString();
                                $xml = $old_xml;
                                $xml = str_ireplace( "object_id=\"$related_object_id\"", "object_id=\"$new_related_object_id\"", $xml );
                                $related_node_array = $related_object->attribute( 'assigned_nodes' );
                                $new_related_node_id = $new_related_object->attribute( 'main_node_id' );
                                foreach ( $related_node_array as $related_node )
                                {
                                    $related_node_id = $related_node->attribute( 'node_id' );
                                    $xml = str_ireplace( "node_id=\"$related_node_id\"", "node_id=\"$new_related_node_id\"", $xml );
                                }
                                if ( $xml != $old_xml )
                                {
                                    $reverse_attribute->fromString( $xml );
                                    $reverse_attribute->store();
                                }
                                break;
                            default:
                        }
                    }
                }
                // Publish new version
                $operationResult = eZOperationHandler::execute( 'content', 'publish', 
                                        array( 'object_id' => $reverse_related_object->attribute( 'id' ),
                                                'version' => $new_version->attribute( 'version' ) ) );

                eZContentCacheManager::clearObjectViewCache( $reverse_related_object->attribute( 'id' ), $new_version->attribute( 'version' ) );
            }
        }
    }
}

?>
