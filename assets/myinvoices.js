function _view_invoice(_reference){        
    if(billing_url && _reference){
        window.open(billing_url+'/view_invoice.php?invoice='+_reference);
        return false;
    }
    return false;
 }    
