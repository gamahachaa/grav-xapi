# Xapi Plugin

**This README.md file should be modified to describe the features, installation, configuration, and general usage of this plugin.**

The **Xapi** Plugin is for [Grav CMS](http://github.com/getgrav/grav). Send usage statement [1;5D[[1;5D[1;5D[1;5D[1;5D[1;5D[1;5D[1;5D[1;5D[1;5D[H[D[D[D[D[D[D[D[D[D[D[D[D[[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[B[F[B[C[C[C[C[C[C[C[C[C[C[C[C[C[C[C[C[C[C[C[C[C[C[C[C[C[CThis plugin s[Dsend XAPI[2~ statements to the LRS of your choice

## Installation

Installing the Xapi plugin can be done in one of two ways. The GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

### GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install xapi

This will install the Xapi plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/xapi`.

### Manual Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `xapi`. You can find these files on [GitHub](https://github.com/bruno-baudry/grav-plugin-xapi) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/xapi
	
> NOTE: This plugin is a modular component for Grav which requires [Grav](http://github.com/getgrav/grav) and the [Error](https://github.com/getgrav/grav-plugin-error) and [Problems](https://github.com/getgrav/grav-plugin-problems) to operate.

### Admin Plugin

If you use the admin plugin, you can install directly through the admin plugin by browsing the `Plugins` tab and clicking on the `Add` button.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/xapi/xapi.yaml` to `user/config/plugins/xapi.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
```

Note that if you use the admin plugin, a file with your configuration, and named xapi.yaml will be saved in the `user/config/plugins/` folder once the configuration is saved in the admin.

## Usage

**Describe how to use the plugin.**

## Credits

**Did you incorporate third-party code? Want to thank somebody?**

## To Do

- [ ] Future plans, if any

