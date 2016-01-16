phpbb-3.1-ext-activation-justification
=========================

How do you know whether or not the person who has just signed up for your board signed up just to spam it? Letting people justify their activation can help you decide exactly who should be activated and who shouldn't be. The extension is only active if user registration in the ACP is set to "By Admin". A port of the phpBB 3.0.x mod of the same name.

[![Build Status](https://travis-ci.org/RMcGirr83/phpBB-3.1-activation-justification.svg?branch=master)](https://travis-ci.org/RMcGirr83/phpBB-3.1-activation-justification)
## Installation

### 1. clone
Clone (or download and move) the repository into the folder ext/rmcgirr83/activationjustification:

```
cd phpBB3
git clone https://github.com/rmcgirr83/phpBB-3.1-activation-justification.git ext/rmcgirr83/activationjustification/
```

### 2. activate
Go to admin panel -> tab customise -> Manage extensions -> enable Activation Justification

## Update instructions:
1. Go to you phpBB-Board > Admin Control Panel > Customise > Manage extensions > Activation Justification: disable
2. Delete all files of the extension from ext/rmcgirr83/activationjustification
3. Upload all the new files to the same locations
4. Go to you phpBB-Board > Admin Control Panel > Customise > Manage extensions > Activation Justification: enable
