RecruitMate
===============

The tool to help members discover and recuit new contributors for exciting PHP packages. 

## Introduction

RecruitMate consists of a simple AngularJS frontend, exposing a publicly visible REST service which allows users to query a store of information about Packagist PHP packages stored on GitHub along with their contributing users.

## The Backend

The RecruitMate backend is a Symfony2 (PHP) based service which queries a GraphDB based triple store. Calculations about a nodes' (user/repository/package) direct neibours, simple routing between neighbours, is specified in SPARQL and carried out on GraphDB. The Symfony controllers then piece this information together using a variety of Breadth-First Search techniques to find shortest paths and nearest neighbours [when needed]

### Populating the Store

The store is populated in three stages. This has primarily been conceved so that in the future these there could work responsivly in sich a way as to load new packages and contributers into the store as they appear. The three stages are all Symfony2 commands, and (for now) use MongoDb for storage. They are as follows:

1. **rasmus:packagist-crawler** - A tool which fetches a list of all of the PHP packages advertised on Packagist, then looks up their related GitHub repository (or ignores the ones whcih don't have GitHub repos) and places this data in Mongo.

2. **rasmus:github-crawler** - Takes the Packagist/GitHub data from Mongo and uses the GitHub API to look up contributirs for each repository. This is then stored in Mongo. Note: there is a 5000 API call per hour limit on GitHub (RecruitMate has a GitHub login) this tool can detect this and handle the failure (and restart) but is up to the sys admin to decide how to restart the process.

3. **rasmus:triple-exporter** - Reads the data from Mongo and produces triples which can be loaded into any store.

### REST Calls

Rest calls are handled by Symfony2 and the FOSRestBundle, NelmioApiDocBundle (unused), and NelmioCorsBundle. The following rest calls are avalible

+ **trace-user?user1=[github_username]&user2=[github_username]** - Traces the path between two GitHub users. If one of the users is invalid, or if no path is found this returns an error message. All messages are provided back as JSON in the form below. This call leverages SPARQL to carry out a fast check on the posibility of a path between the two users so that negative responses are provided very quickly.

```json
{  
  meta:{  
    status:200,
    link:[  
      {  
        rel:"self",
        href:"http://api.recruitmate.greydog.co/trace-user?user1=GromNaN&user2=tonydspaniard"
      }
    ]
  },
  data:{  
    message:"Search complete",
    path_found:true,
    paths:[  
      {  
        type:"contributor",
        id:"GromNaN",
        order:0,
        link:{  
          rel:"self",
          href:"http://github.com/GromNaN"
        }
      },
      {  
        type:"repository",
        id:"monofone/ansible-installer",
        order:1,
        link:{  
          rel:"self",
          href:"http://github.com/monofone/ansible-installer"
        }
      },
      {  
        type:"contributor",
        id:"schmunk42",
        order:2,
        link:{  
          rel:"self",
          href:"http://github.com/schmunk42"
        }
      },
      {  
        type:"repository",
        id:"schmunk42/yii2-packaii",
        order:3,
        link:{  
          rel:"self",
          href:"http://github.com/schmunk42/yii2-packaii"
        }
      },
      {  
        type:"contributor",
        id:"tonydspaniard",
        order:4,
        link:{  
          rel:"self",
          href:"http://github.com/tonydspaniard"
        }
      }
    ]
  }
}
```

+ **/find-contributors?package=[packagist_packagename]** - Uses a BFS approch starting with the existing contributors to find connections who may be willing to join. Suggestions are ranked by the strength of their association with existing users. For example if an existing user and a 3rd party work on lots of the same projects they will have a string bond, equaly, is two of the existing users have a connection with the same person (even if this routes through different projects) they will again have a stronger bond. All responses come in JSON in the form bellow.
  + **Paging** - find-contributors allows management of the number of suggested user returned through paging the query parameter *?page=[page_number]* can be used to select a page, and the query parameter *?per_page=[number_per_page]* can be used to list the number of suggestions listed per page.

```json
{  
  meta:{  
    status:200,
    page:1,
    per_page:10,
    total:487,
    link:[  
      {  
        rel:"self",
        href:"http://api.recruitmate.greydog.co/find-contributors?package=los%2Flosbase"
      }
    ]
  },
  data:{  
    message:"Search complete",
    contributor:[  
      {  
        type:"contributor",
        username:"BinaryKitten",
        order:1,
        link:{  
          rel:"self",
          href:"http://github.com/BinaryKitten"
        }
      },
      {  
        type:"contributor",
        username:"MAXakaWIZARD",
        order:2,
        link:{  
          rel:"self",
          href:"http://github.com/MAXakaWIZARD"
        }
      },
      {  
        type:"contributor",
        username:"mdestagnol",
        order:3,
        link:{  
          rel:"self",
          href:"http://github.com/mdestagnol"
        }
      },
      {  
        type:"contributor",
        username:"patrickli",
        order:4,
        link:{  
          rel:"self",
          href:"http://github.com/patrickli"
        }
      },
      {  
        type:"contributor",
        username:"cjunge",
        order:5,
        link:{  
          rel:"self",
          href:"http://github.com/cjunge"
        }
      },
      {  
        type:"contributor",
        username:"jeffxpx",
        order:6,
        link:{  
          rel:"self",
          href:"http://github.com/jeffxpx"
        }
      },
      {  
        type:"contributor",
        username:"joshuajabbour",
        order:7,
        link:{  
          rel:"self",
          href:"http://github.com/joshuajabbour"
        }
      },
      {  
        type:"contributor",
        username:"maximebf",
        order:8,
        link:{  
          rel:"self",
          href:"http://github.com/maximebf"
        }
      },
      {  
        type:"contributor",
        username:"dmelo",
        order:9,
        link:{  
          rel:"self",
          href:"http://github.com/dmelo"
        }
      },
      {  
        type:"contributor",
        username:"dlu-gs",
        order:10,
        link:{  
          rel:"self",
          href:"http://github.com/dlu-gs"
        }
      }
    ]
  }
}
```

### Limitations

There is a limitation set in the SPARQL that for any given query a max of 5000 results can be returned. This should not pose a problem for 99.9% of users and can be altered if it does.

## The Frontend

The frontend is a lightweight responsive AngularJS app (optimised for mobile and desktop). For more information visit recruitmate.greydog.co
