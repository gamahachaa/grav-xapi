# Xapi Plugin

The **Xapi** Plugin is for [Grav CMS](http://github.com/getgrav/grav) to send XAPI (Tincan) statements to an LRS. So far this plugin only sends statements. It doesn't provide yet any LRS query.

How does it works :
- When a user opens a page: 
  a [statement](https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-About.md#def-statement) is sent to the LRS. You can map a group of users to different LRS. You can map the page template to [verbs](https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#verb) and/or [activityTypes](https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#2441-when-the-objecttype-is-activity)
- When a from is submited:
  You can add a xapi process to a form so that its submission triggers a statement.

- You can add js script to your pages/templates to configure trigger special statements.
  _Nothing more is configured for tincanjs yet. Opened toproposals_

## Installation

Installing the Xapi plugin can be done in one of two ways. The GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

### GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install xapi

This will install the Xapi plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/xapi`.

### Manual Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `xapi`. You can find these files on [GitHub](https://github.com/gamahachaa/grav-xapi) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/xapi
	
> NOTE: This plugin is a modular component for Grav which requires 
- [Grav](http://github.com/getgrav/grav) 
- [Error](https://github.com/getgrav/grav-plugin-error) 
- [Problems](https://github.com/getgrav/grav-plugin-problems) 
- [Login](https://github.com/getgrav/grav-plugin-login)
to operate.

### Admin Plugin

If you use the admin plugin, you can install directly through the admin plugin by browsing the `Plugins` tab and clicking on the `Add` button.

**Note**
The admin blueprints is experimental. As I manage all my grav with Yaml files not using the admin, it might have some flaws. Feel free to submit PR to enhance it.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/xapi/xapi.yaml` to `user/config/plugins/xapi.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
php:
  active: true
js:
  active: false
lrs:
    -
        naming: default
        endpoint: "https://mylrs/data/xAPI"
        username: 3b5d9d1f2fc76e9cdb663f311613d5663fc694d1
        password: fa4a6689c00153b393905e095f44510d34138195
        version: "1.0.1"
template_activityType:
    -
        naming: default
        activityIRI: "http://activitystrea.ms/schema/1.0/page"
template_verb:
    -
        naming: default
        verbIRI: "http://activitystrea.ms/schema/1.0/read"

filter:
  template:
    - login
  taxonomies:
    category:
      - reporting
  users:
    - jdoe
  groups:
    - management
  uri:
    query:
        -
            key: search
            value: test

```

Note that if you use the admin plugin, a file with your configuration, and named xapi.yaml will be saved in the `user/config/plugins/` folder once the configuration is saved in the admin.

## Usage

### Activity / Object

[Xapi object](https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#object)
The statement's object is defined by the page's route.
_ nothing configurable yet but opened to any proposal_

### Context 

[xapi context](https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#context)
Is defined by the site's title and the current language.

_ nothing configurable yet but opened to any proposal_

### JS

Activating the tincan js will add the [minified TinCanJS library](http://rusticisoftware.github.io/TinCanJS/)to the page html head, then up to you to configre and trigger the statememnts.

### lrs

A sequence of 
- **Naming** As [group](https://learn.getgrav.org/16/advanced/groups-and-permissions) Naming to a group so that when set to 
- **endpoint** [see xapi doc](https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-About.md#def-endpoint)
- Credentials to connect to it 
	- username
	- password
- **version** (the LRS's xapi version)

A **default** setup is mandatory.
You can add as many LRS as you want

```yaml
lrs:
    - 
      naming: default
      endpoint: https://my.lrs.test/data/xAPI/
      username: 3b5d9d1f2fc76ed5663fc694d19cdb663f311613
      password: fa4a6689c001534510d34138195b393905e095f4
      version: "1.0.0"
    - 
      naming: new_starters
      endpoint: https://my.lrs.other/data/xAPI/
      username: 3f311613d5663fc694d13b5d9d1f2fc76e9cdb66
      password: 905e095f44510d34138195fa4a6689c00153b393
      version: "1.0.1"      
```

So if a user has group 'new_starter0 statements will be sent to that LRS else to the default one.

### template_verb

There you can define a sequence of [verbs](https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#verb) to match a pages templates
If not found default is chosen

```yaml
template to verb:
  -
    naming: default
    home: http://adlnet.gov/expapi/verbs/experienced
  -
    naming: listing
    verbIRI: 'http://activitystrea.ms/schema/1.0/search'

```

### template to activityType

There you can define a sequence of [activityTypes](https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#2441-when-the-objecttype-is-activity) to match a pages templates
If not found default is chosen

```yaml
template_verb:
    -
        naming: default
        verbIRI: 'http://activitystrea.ms/schema/1.0/read'
    -
        naming: listing
        verbIRI: 'http://activitystrea.ms/schema/1.0/search'
    
```

### filters

Exclusive filter to prevent tracking usage of :

- page
	- template
	- taxonomies
- user
  - login
  - group
- URL
  - routes
  - queries

```yaml
filter:
  template:
    - login
  taxonomies:
    category:
      - reporting
  users:
    - jdoe
  groups:
    - management
  uri:
    query:
        -
            key: search
            value: test
```

### Forms

Just add in your form's process definition form processing result to match a [xapi extension](https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#miscext)

```yaml
    process:
        xapi:
            extensions:
                _id: "{{ grav.user.username }}{{now|date('HisDdMy') }}"
                sourceFiles: sourceFiles
                totalWords: totalWords
                targetLangs: targetLangs
                http://id.tincanapi.com/extension/purpose: todo
                targetDocType: targetDocType
                http://id.tincanapi.com/extension/date: deadline
                http://id.tincanapi.com/extension/severity: criticality
                comments: comments
                sourceLang: sourceLang
                onbehalf: '{{ (form.value.onbehalf == "Myself" or form.value.onbehalf is null)? grav.user.email : form.value.onbehalf~"@salt.ch"|raw}}'
```


## Credits
Uses the all mighty https://rusticisoftware.com/ PHP lib.
https://github.com/RusticiSoftware/TinCanPHP


## To Do

- Page level profiles
- extend templates with js tiggers
- use lrs response to filter tracking
- 
- ...