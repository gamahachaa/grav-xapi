# Grav-Xapi Plugin

**This README.md file should be modified to describe the features, installation, configuration, and general usage of this plugin.**

The **Grav-Xapi** Plugin is for [Grav CMS](http://github.com/getgrav/grav) to send XAPI (Tincan) statements to an LRS

## Installation

Installing the Xapi plugin can be done in one of two ways. The GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

### GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install grav-xapi

This will install the Xapi plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/grav-xapi`.

### Manual Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `xapi`. You can find these files on [GitHub](https://github.com/gamahachaa/grav-xapi) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/grav-xapi
	
> NOTE: This plugin is a modular component for Grav which requires 
- [Grav](http://github.com/getgrav/grav) 
- [Error](https://github.com/getgrav/grav-plugin-error) 
- [Problems](https://github.com/getgrav/grav-plugin-problems) 
- [Login](https://github.com/getgrav/grav-plugin-login)
to operate.

### Admin Plugin

If you use the admin plugin, you can install directly through the admin plugin by browsing the `Plugins` tab and clicking on the `Add` button.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/grav-xapi/grav-xapi.yaml` to `user/config/plugins/grav-xapi.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
lrs:
    default:
      endpoint: https://my.lrs.test/data/xAPI/
      username: 
      password: 
verb:
    default: http://adlnet.gov/expapi/verbs/experienced
filter:
    template:
        - login
    taxonomies:
       category: 
       tag:
       authors:
    users:
        - jdoe
    groups:
        - admins
```

Note that if you use the admin plugin, a file with your configuration, and named grav-xapi.yaml will be saved in the `user/config/plugins/` folder once the configuration is saved in the admin.

## Usage

### lrs
A collection of 
- [endpoint](https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-About.md#def-endpoint)
- Credentials to connect to it 
	- username
	- password

The default setup is mandatory.
You can add as many LRS as you want then add users to the named group expl:
```yaml
lrs:
    default:
      endpoint: https://my.lrs.test/data/xAPI/
      username: lskdfjlkdfjéa
      password: alédsfjkaàsld
    special_tracker:
      endpoint: https://my.other.lrs.test/data/
      username: adfjéalksdfjé
      password: éaksdjféalkds
```
Now when a user accesses a page the plugin will try to match the first found group of the logedin user to an LRS then connect to it to store the statements.
here if JDOE is in **special_tracker** statements will be stored there else to the default LRS.

### verb

There you can define a collection of verb to match a page template
If not found default is chosen
```yaml
verb:
    default: http://activitystrea.ms/schema/1.0/read
    home: http://adlnet.gov/expapi/verbs/experienced
```

### filter
Exclusive filter to prevent tracking usage of
- page
	- template
	- taxonomies
- user
- group of users
```yaml
filter:
    template:
        - login
        - listings
    taxonomies:
       category: 
       tag: ['fun']
       authors:
    users:
        - jdoe
    groups:
        - admins
```

## Credits
Uses the all mighty https://rusticisoftware.com/ PHP lib.
https://github.com/RusticiSoftware/TinCanPHP


## To Do

- [ ] Page level statement definition
- [ ] add JS libs to track page interaction
- [ ] extend Forms plugin

