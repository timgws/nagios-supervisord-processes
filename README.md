
# check_supervisor

check_supervisor is a plugin for use with Nagios that uses supervisor's [XML-RPC API][1] to check
the status of any processes that are set up to be running inside supervisor.

Code was forked from `blablacar` (https://github.com/blablacar/nagios-supervisord-processes) and
updated a little bit to clean up documentation & code.

# Nagios Configuration
**Command Configuration** (commands.cfg):

    define command {
            command_name                    check_supervisor
            command_line                    $USER1$/check_supervisor -H $HOSTNAME$ -p $ARG1$
            register                        1
    }

**Service Template** (servicetemplates.cfg):

    define service {
            name                            supervisor_check
            hostgroup_name                  Supervisors
            display_name                    Supervisor Checks
            servicegroups                   has_supervisor
            check_command                   check_supervisor
            initial_state                   o
            max_check_attempts              2
            check_interval                  2
            retry_interval                  1
            check_period                    24x7
            notification_interval           0
            first_notification_delay        5
            notification_period             24x7
            notification_options            w,c,r
            notifications_enabled           1
            contact_groups                  help
            register                        0
    }

I [wrote about the nagios plugin over at my blog][2].

  [1]: http://supervisord.org/api.html
  [2]: http://timg.ws/2014/06/03/nagios-plugin-for-checking-the-status-of-supervisord-processes/
