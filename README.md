# SWORD Client Plugin

Allow Journal Managers and (optionally) Authors to deposit articles via the SWORD protocol

## Installation

This plugin is best installed using the Plugin Gallery from within Open Journal Systems.
Log in as Administrator, navigate to `Settings` > `Website` > `Plugins`, and open the "Plugin Gallery"
tab. Find the Sword client plugin listed there and use the `Install` button to install it.

## Configuration

### General Settings
- After successful installation, you  have to **enable** the plugin in  generic plugin section.
- A new tab, **Sword Settings** will be available next to Website Settings -> plugins
- After clicking the tab, add a new Deposit point using the **Create Deposit Point**
- Add your Service Provider Information there. 
  -   Name: Customized name
  -   Deposit Point URL: Service Document name :  Depending on the deposit point type, the URL will either need to be the service document or the deposit point URL. See the documentation on the form about this.  e.g.http://demo.dspace.org/swordv2/servicedocument
  -   Username: Username
  -   Password: Password
  -   API Key: Some repository providers may supply you an API key.
  -   Type: select your requirement.
-   Save and Exit
  
 ### Import/Export Plugin
 - Select Sword Plugin in Import/Export Plugins
 - Got to **Import/Export Data**
 - Select the **configured**  Deposit Point
 - Avaliable Collections will be available under **Deposit Point**
 - Choose the options either  Deposit Galleys / Deposit Most Recent Editorial File or both.
 - Select the articles
 - Click deposit

  
