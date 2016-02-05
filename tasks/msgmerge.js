'use strict';

module.exports = function(grunt) {

  // use `msginit -i custom-logging-service.pot -l <ll_CC> -o custom-logging-service-<ll_CC>.po` to start new translations
  
  grunt.registerMultiTask('msgmerge', 'update .po files from template', function() {
    var options = this.options({
        text_domain: 'messages',
        template: './',
        version: 'VERSION',
    });
    var dateFormat = require('dateformat');

    if( grunt.file.isDir(options.template) ) {
        options.template = options.template.replace(/\/$/, '') + '/' + options.text_domain + '.pot';
    }

    if( !grunt.file.exists(options.template) ) {
        grunt.fail.warn('Template file not found: ' + options.template, 3);
    }

    grunt.verbose.writeln('Template: ' + options.template);
    
    var done = this.async();
    var counter = this.files.length;

    this.files.forEach(function(file) {

      grunt.util.spawn( {
        cmd: 'msgmerge',
        args: [file.src, options.template]
      }, function(error, result, code){

        grunt.verbose.write('Updating: ' + file.src + ' ...');

        if (error) {
            grunt.verbose.error();
        } else {
            var regexp = /(Project-Id-Version: crosswordsearch ).*(\\n)/;
            var rpl = '$1' + options.version + '$2';
            var content = String(result).replace(regexp, rpl);
            regexp = /(PO-Revision-Date: ).*(\\n)/;
            rpl =  '$1' + dateFormat(new Date(), 'yyyy-mm-dd HH:MMo') + '$2';
            content = content.replace(regexp, rpl);
            grunt.file.write(file.src[0], content);
            grunt.verbose.ok();
        }

        counter--;

        if (error || counter === 0) {
            done(error);
        }
      });
    });
  });
};
