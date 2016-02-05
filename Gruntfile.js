module.exports = function(grunt) {

  var text_domain = 'custom-logging-service',
      l10ndir = 'languages/';

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    pot: {
        options: {
            text_domain: text_domain,
            copyright_holder: 'Claus Colloseus <ccprog@gmx.de>',
            dest: l10ndir,
            encoding: 'UTF-8',
            overwrite: true,
            keywords: [
                '__:1,2t',
                '_e:1,2t',
                '_x:1,2c,3t',
                'esc_html__:1,2t',
                'esc_html_e:1,2t',
                'esc_html_x:1,2c,3t',
                'esc_attr__:1,2t', 
                'esc_attr_e:1,2t', 
                'esc_attr_x:1,2c,3t', 
                '_ex:1,2c,3t',
                '_n:1,2,3t', 
                '_nx:1,2,4c,5t',
                '_n_noop:1,2,3t',
                '_nx_noop:1,2,3c,4t'
            ],
        },
        files:{
            src:  [ '*.php', 'includes/*.php' ],
            expand: true
        }
    },
    msgmerge: {
      options: {
        text_domain: text_domain,
        template: l10ndir,
        version: '<%= pkg.version %>'
      },
      files: {
        src: l10ndir + text_domain + '-*.po',
        expand: true
      }
    },
    po2mo: {
      files: {
        src: l10ndir + '*.po',
        expand: true
      },
    }
  });

  grunt.loadNpmTasks('grunt-pot');
  grunt.loadNpmTasks('grunt-po2mo');
  grunt.task.loadTasks('tasks/');

  grunt.registerTask('msgupdate', ['pot', 'msgmerge']);
};
