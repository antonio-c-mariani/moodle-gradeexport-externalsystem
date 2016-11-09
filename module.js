M.gradeexport_externalsystem = {};

M.gradeexport_externalsystem.init = function(Y) {

    Y.on('click', function(e) {
        id = this.get('id');
        res = id.split("_");
        idsend = '#send_' + res[res.length-1];
        Y.all(idsend).each(function() {
            this.set('checked', 'checked');
        });
    }, '.gradeexport_externalsystem_editable');

};
