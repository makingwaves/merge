Merge eZ Publish extension
==========================

Introduction
--------------------------

Will let you merge two different content objects which you wish where the same object. Used to
restructure and maintain your content tree.


Merge two content objects
--------------------------

This extension is used by going to Admin -> Setup -> Merge objects, and choosing two content objects
of the same class with the browse button. You can then select the master object, which is the object
you will end up with after the merge. You can also choose which content to keep based on the 
different existing translations in the two objects. When ready, click button to do the merging.


Supported features
--------------------------

Merge will copy selected content translations from the different attributes to the master
content object ("Merge to..." column). This will be done by adding a new version on the master.
It will use the builtin toString()/fromString() functionality for the copying.

For attributes of the object relation list datatype it will merge the relations from the two
objects into the master (sort order will be master relations first, then slave relations).

Any reverse related objects will be updated to relate to the master object instead of the slave.
This supports attributes of the object relation list datatype, single object relations, and
XML fields. The children of any slave nodes will be moved to the master main node.

Each translation from the two objects will be created on the master object.

The slave object will be removed after the merge.


Todo
--------------------------

The merge does not support url alias history. When slave object is merged into master, url aliases
should be updated to forward any urls to the master object.


Contributors
--------------------------

Written by Arne Bakkebø, [Making Waves](http://www.makingwaves.no/), on behalf of
[Norwegian Seafood Council](http://seafood.no/), who required it to restructure their content.

[Norwegian Seafood Council](http://seafood.no/) have the following eZ Publish sites around the globe:
* [Norway](http://godfisk.no/)
* [Sweden](http://norskfisk.se/)
* [USA](http://salmonfromnorway.com/)
* [Spain](http://www.mardenoruega.es/)
* [Portugal](http://www.mardanoruega.com/)
* [Brazil](http://bacalhaudanoruega.com.br/)
* [Italy](http://fiordisapori.it/)
* [France](http://poissons-de-norvege.fr/)
* [Russia](http://iznorvegii.ru/)
* [Germany](http://norwegenfisch.de/)
* [Japan](http://seafoodfromnorway.jp/)
