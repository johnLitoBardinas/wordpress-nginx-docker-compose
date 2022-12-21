let fs = require('fs')

let file = './.env.example'
let outFile = './.env'
let templateRegex = /__.*__/g

let content = fs.readFileSync(file, {
    encoding: 'utf8'
})

function onlyUnique(value, index, self) {
    return self.indexOf(value) === index
}

let replacerArray = content.match(templateRegex).filter(onlyUnique)
let envName = process.env.BITBUCKET_BRANCH

replacerArray.forEach((replacerString) => {
    let reg = new RegExp(replacerString, 'g')
    let envVarName = replacerString.slice(2, -2)
    let stageEnvVarName = envName.toUpperCase() + '_' + envVarName
    let envVar

    if (process.env[stageEnvVarName]) {
        envVar = process.env[stageEnvVarName]
    } else if (process.env[envVarName]) {
        envVar = process.env[envVarName]
    } else {
        throw new Error(`Environment variable ${envVarName} does not exist`)
    }

    content = content.replace(reg, envVar)
})

fs.writeFileSync(outFile, content)