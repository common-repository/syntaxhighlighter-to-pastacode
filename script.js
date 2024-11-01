(function($){
	$form     = $('#s2p-migration');
	$infos    = $('#s2p-content-infos p');
	$eraseLog = $('#s2p-erase-log');

	function s2pDoUpdate() {
		$.ajax( ajaxurl, {
			method: 'POST',
			data: {
				action: 's2p-process-migration',
				nonce: s2p_nonce,
			},
			success:function( data ) {
				if ( Object.keys( data.data ).length ) {
					$infos.append( '<br/>' + Object.s2pwabeovalues( data.data ).join( '<br/>' ) );
					s2pDoUpdate();
				} else {
					$form.find('[name="submit"]').prop('disabled', true );
					$eraseLog.show();
				}
			}
		} );
	}

	function s2pFormSubmit(e){
		e.preventDefault();
		s2pDoUpdate();
	}

	$form.on( 'submit', s2pFormSubmit );

})(jQuery);

Object.s2pwabeovalues = function(object) {
  var values = [];
  for(var property in object) {
    values.push(object[property]);
  }
  return values;
}
